<?php

namespace App\Integrations\TenderSources\Providers\PLACSP;

use App\Integrations\TenderSources\Contracts\TenderSourceProvider;
use App\Integrations\TenderSources\DownloadedDocument;
use App\Models\Offer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider para la Plataforma de Contratacion del Sector Publico (PLACSP)
 * y todas las plataformas agregadas que publican en contrataciondelestado.es
 * (Euskadi, Madrid, Andalucia, Galicia, municipales federados...).
 *
 * PLACSP NO expone documentos en el HTML publico: todo pasa por la
 * sindicacion oficial y esa requiere **certificado digital** reconocido
 * (FNMT, Izenpe, Camerfirma, etc.) instalado en el cliente HTTP.
 *
 * Flujo:
 *   1. De la URL de la oferta extraemos `idEvl`.
 *   2. Pedimos el Codice XML del expediente con cert mTLS.
 *   3. Parseamos <cac:AdditionalDocumentReference> y sacamos URLs de PDFs.
 *   4. Descargamos cada pliego (tambien via mTLS).
 *
 * Config:
 *   config('services.placsp.cert_path')     -> ruta al .p12 (fuera de /public)
 *   config('services.placsp.cert_password') -> password del .p12 (en .env)
 */
class PLACSPProvider implements TenderSourceProvider
{
    /** Hosts que PLACSP agrega. Todos se tratan con el mismo parser. */
    private const SUPPORTED_HOSTS = [
        'contrataciondelestado.es',
        'www.contrataciondelestado.es',
        'www.juntadeandalucia.es',
        'www.contratacion.euskadi.eus',
        'contratos-publicos.comunidad.madrid',
        'www.madrid.org',
        'www.contratosdegalicia.gal',
        'hacienda.navarra.es',
        'licitacions.bcn.cat',
        'sedeelectronica.aviles.es',
        'seu.portsdebalears.gob.es',
    ];

    private const USER_AGENT         = 'Mozilla/5.0 (compatible; CRM-Autoritas/1.0; +https://crm.app-util.com)';
    private const MAX_BYTES_PER_FILE = 50 * 1024 * 1024; // 50 MB
    private const HTTP_TIMEOUT       = 45;

    /** Endpoint de Codice XML por expediente (requiere cert). */
    private const CODICE_EXPEDIENTE_URL = 'https://contrataciondelestado.es/sindicacion/sindicacion_643/expediente';

    public function id(): string    { return 'PLACSP'; }
    public function label(): string { return 'Plataforma de Contratación del Sector Público'; }

    public function supports(string $tenderUrl): bool
    {
        $host = parse_url($tenderUrl, PHP_URL_HOST) ?: '';
        return in_array(strtolower($host), self::SUPPORTED_HOSTS, true);
    }

    public function fetchDocuments(Offer $offer): array
    {
        $url = $offer->url ?: '';
        if (! $url || ! $this->supports($url)) {
            throw new \RuntimeException("PLACSP: URL no soportada: {$url}");
        }

        $idEvl = $this->extractIdEvl($url);
        if (! $idEvl) {
            throw new \RuntimeException("PLACSP: no se pudo extraer idEvl de la URL");
        }

        $this->assertCertConfigured();

        // 1) Descargar Codice XML del expediente.
        $xml = $this->fetchCodiceXml($idEvl);

        // 2) Parsear y sacar URLs de documentos.
        $links = $this->extractDocumentLinksFromCodice($xml);

        if (empty($links)) {
            Log::info('PLACSP: Codice sin documentos', ['offer_id' => $offer->id, 'idEvl' => $idEvl]);
            return [];
        }

        // 3) Descargar cada documento.
        $docs = [];
        foreach ($links as $link) {
            try {
                $docs[] = $this->downloadDocument($link);
            } catch (\Throwable $e) {
                Log::warning('PLACSP: fallo descargando pliego', [
                    'offer_id' => $offer->id, 'link' => $link, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $docs;
    }

    /** Extrae idEvl de una URL PLACSP (?idEvl=base64urlencoded==). */
    private function extractIdEvl(string $url): ?string
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $q);
        return $q['idEvl'] ?? null;
    }

    private function assertCertConfigured(): void
    {
        $path = config('services.placsp.cert_path');
        $pass = config('services.placsp.cert_password');

        if (! $path || ! is_file($path)) {
            throw new \RuntimeException(
                "PLACSP: falta el certificado digital. Configura PLACSP_CERT_PATH (actual: {$path})."
            );
        }
        if (! is_readable($path)) {
            throw new \RuntimeException("PLACSP: el certificado {$path} no es legible (permisos).");
        }
        if ($pass === null || $pass === '') {
            throw new \RuntimeException("PLACSP: falta PLACSP_CERT_PASSWORD en .env.");
        }
    }

    private function fetchCodiceXml(string $idEvl): string
    {
        $url = self::CODICE_EXPEDIENTE_URL . '?idEvl=' . rawurlencode($idEvl);

        $resp = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept'     => 'application/xml,text/xml',
            ])
            ->timeout(self::HTTP_TIMEOUT)
            ->withOptions($this->curlCertOptions() + ['allow_redirects' => true])
            ->get($url);

        if (! $resp->ok()) {
            throw new \RuntimeException("PLACSP: HTTP {$resp->status()} pidiendo Codice del expediente");
        }

        $body = $resp->body();
        // Detecta la pagina de error "Su certificado no esta autorizado".
        if (stripos($body, 'no est') !== false && stripos($body, 'autorizado') !== false) {
            throw new \RuntimeException("PLACSP: el certificado no esta autorizado a acceder a la plataforma");
        }

        return $body;
    }

    /**
     * Parseador de Codice / UBL: extrae URLs de <cbc:URI> dentro de
     * <cac:AdditionalDocumentReference> o estructuras similares.
     *
     * @return string[] URLs unicas a los documentos.
     */
    private function extractDocumentLinksFromCodice(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        if (! $doc->loadXML($xml)) {
            libxml_clear_errors();
            // No es XML valido: fallback a regex por si viniese algun enlace directo
            return $this->fallbackRegexLinks($xml);
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        // Registramos los namespaces habituales de Codice/UBL.
        $xpath->registerNamespace('cac', 'urn:dgpe:names:draft:codice:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:dgpe:names:draft:codice:schema:xsd:CommonBasicComponents-2');
        // UBL estandar tambien se usa a veces
        $xpath->registerNamespace('cac2', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc2', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $urls = [];

        // Query amplia: cualquier URI dentro del documento, con namespace o sin.
        $nodes = $xpath->query('//*[local-name()="URI"]');
        if ($nodes !== false) {
            foreach ($nodes as $n) {
                $val = trim($n->textContent);
                if ($val === '') continue;
                if (! preg_match('#^https?://#i', $val)) continue;
                // Quedarnos solo con documentos descargables.
                if (preg_match('#\.(pdf|zip|docx?|xlsx?|rtf|odt|ods)(\?|$)#i', $val) ||
                    preg_match('#/wps/wcm/connect/#i', $val)) {
                    $urls[$val] = true;
                }
            }
        }

        // Si el XPath no encuentra nada util, complementamos con regex global.
        if (empty($urls)) {
            foreach ($this->fallbackRegexLinks($xml) as $u) $urls[$u] = true;
        }

        return array_keys($urls);
    }

    /** @return string[] */
    private function fallbackRegexLinks(string $payload): array
    {
        preg_match_all('#https?://[^\s"<>]+?\.(pdf|zip|docx?|xlsx?|rtf)(\?[^\s"<>]*)?#i', $payload, $m);
        return array_values(array_unique($m[0] ?? []));
    }

    private function downloadDocument(string $url): DownloadedDocument
    {
        $resp = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept'     => '*/*',
            ])
            ->timeout(self::HTTP_TIMEOUT)
            ->withOptions($this->curlCertOptions() + ['allow_redirects' => true])
            ->get($url);

        if (! $resp->ok()) {
            throw new \RuntimeException("HTTP {$resp->status()} descargando {$url}");
        }

        $bytes = $resp->body();
        if (strlen($bytes) > self::MAX_BYTES_PER_FILE) {
            throw new \RuntimeException('Fichero demasiado grande: ' . strlen($bytes) . ' bytes');
        }

        return new DownloadedDocument(
            sourceUrl: $url,
            filename:  $this->deriveFilename($url, $resp->header('Content-Disposition') ?: ''),
            content:   $bytes,
            mime:      $resp->header('Content-Type') ?: null,
        );
    }

    /**
     * Opciones cURL para autenticacion mTLS con el .p12.
     * Guzzle las acepta bajo la clave 'curl'.
     *
     * @return array{curl: array<int,mixed>}
     */
    private function curlCertOptions(): array
    {
        return [
            'curl' => [
                CURLOPT_SSLCERT        => config('services.placsp.cert_path'),
                CURLOPT_SSLCERTTYPE    => 'P12',
                CURLOPT_SSLCERTPASSWD  => config('services.placsp.cert_password'),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ],
        ];
    }

    private function deriveFilename(string $url, string $contentDisposition): string
    {
        if ($contentDisposition !== '' &&
            preg_match('/filename\*?=(?:UTF-8\'\')?"?([^"]+)"?/i', $contentDisposition, $m)) {
            return $this->sanitizeFilename(rawurldecode($m[1]));
        }

        $base = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if ($base === '') return 'pliego_' . substr(md5($url), 0, 8) . '.pdf';
        return $this->sanitizeFilename($base);
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $name) ?: 'pliego.pdf';
        return mb_substr($name, 0, 200);
    }
}
