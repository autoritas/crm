<?php

namespace App\Integrations\TenderSources\Providers\PSCP;

use App\Integrations\TenderSources\Contracts\TenderSourceProvider;
use App\Integrations\TenderSources\DownloadedDocument;
use App\Models\Offer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider para la Plataforma de Serveis de Contractacio Publica (PSCP)
 * de la Generalitat de Catalunya.
 *
 * Hosts: contractaciopublica.cat, contractaciopublica.gencat.cat.
 *
 * Estrategia similar a PLACSP: GET del detalle (`notice.pscp?idDoc=<N>`),
 * parseamos el HTML y extraemos los enlaces a documentos. PSCP los suele
 * servir bajo `/ecofin_sobres/AppJava/downloadFile.pscp?...` o similar.
 */
class PSCPCatalunyaProvider implements TenderSourceProvider
{
    private const SUPPORTED_HOSTS = [
        'contractaciopublica.cat',
        'www.contractaciopublica.cat',
        'contractaciopublica.gencat.cat',
    ];

    private const USER_AGENT         = 'Mozilla/5.0 (compatible; CRM-Autoritas/1.0; +https://crm.app-util.com)';
    private const MAX_BYTES_PER_FILE = 50 * 1024 * 1024;
    private const HTTP_TIMEOUT       = 30;

    public function id(): string    { return 'PSCP'; }
    public function label(): string { return 'PSCP Catalunya'; }

    public function supports(string $tenderUrl): bool
    {
        $host = parse_url($tenderUrl, PHP_URL_HOST) ?: '';
        return in_array(strtolower($host), self::SUPPORTED_HOSTS, true);
    }

    public function fetchDocuments(Offer $offer): array
    {
        $url = $offer->url ?: '';
        if (! $url || ! $this->supports($url)) {
            throw new \RuntimeException("PSCP: URL no soportada: {$url}");
        }

        $html = $this->fetchHtml($url);
        $documentLinks = $this->extractDocumentLinks($html, $url);

        if (empty($documentLinks)) {
            Log::info('PSCP: sin pliegos detectados', ['offer_id' => $offer->id, 'url' => $url]);
            return [];
        }

        $docs = [];
        foreach ($documentLinks as $link) {
            try {
                $docs[] = $this->downloadDocument($link);
            } catch (\Throwable $e) {
                Log::warning('PSCP: fallo descargando pliego', [
                    'offer_id' => $offer->id,
                    'link'     => $link,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $docs;
    }

    private function fetchHtml(string $url): string
    {
        $resp = Http::withHeaders([
                'User-Agent'      => self::USER_AGENT,
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ca-ES,es-ES;q=0.9,es;q=0.8',
            ])
            ->timeout(self::HTTP_TIMEOUT)
            ->withOptions(['allow_redirects' => true])
            ->get($url);

        if (! $resp->ok()) {
            throw new \RuntimeException("PSCP: HTTP {$resp->status()} al pedir detalle");
        }

        return $resp->body();
    }

    /**
     * Extrae enlaces a documentos del HTML de un `notice.pscp`.
     *
     * PSCP suele listar documentos en una tabla con enlaces tipo:
     *   /ecofin_sobres/AppJava/downloadFile.pscp?idDoc=...&reqCode=file
     * o directos a PDFs servidos por ellos.
     *
     * @return string[]
     */
    private function extractDocumentLinks(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $query = '//a[
            contains(@href, "downloadFile.pscp") or
            contains(@href, "/ecofin_sobres/") or
            substring(@href, string-length(@href)-3) = ".pdf" or
            substring(@href, string-length(@href)-3) = ".zip" or
            substring(@href, string-length(@href)-3) = ".doc" or
            substring(@href, string-length(@href)-4) = ".docx"
        ]';

        $urls = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '') continue;

            $absolute = $this->toAbsoluteUrl($href, $baseUrl);

            // Descarta enlaces de navegacion interna obvia (home, buscador...).
            if (preg_match('#(/home|/search|/buscador|/login|/register)#i', $absolute)) continue;

            $urls[$absolute] = true;
        }

        return array_keys($urls);
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $href)) return $href;

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host   = $base['host']   ?? '';

        if (str_starts_with($href, '//')) return $scheme . ':' . $href;
        if (str_starts_with($href, '/'))  return $scheme . '://' . $host . $href;

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
        return $scheme . '://' . $host . $basePath . '/' . $href;
    }

    private function downloadDocument(string $url): DownloadedDocument
    {
        $resp = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept'     => '*/*',
            ])
            ->timeout(self::HTTP_TIMEOUT)
            ->withOptions(['allow_redirects' => true])
            ->get($url);

        if (! $resp->ok()) {
            throw new \RuntimeException("HTTP {$resp->status()} descargando {$url}");
        }

        $bytes = $resp->body();
        if (strlen($bytes) > self::MAX_BYTES_PER_FILE) {
            throw new \RuntimeException('Fichero demasiado grande: ' . strlen($bytes) . ' bytes');
        }

        $filename = $this->deriveFilename($url, $resp->header('Content-Disposition') ?: '');

        return new DownloadedDocument(
            sourceUrl: $url,
            filename:  $filename,
            content:   $bytes,
            mime:      $resp->header('Content-Type') ?: null,
        );
    }

    private function deriveFilename(string $url, string $cd): string
    {
        if ($cd !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^"]+)"?/i', $cd, $m)) {
            return $this->sanitizeFilename(rawurldecode($m[1]));
        }
        $base = basename(parse_url($url, PHP_URL_PATH) ?: '');
        return $base !== ''
            ? $this->sanitizeFilename($base)
            : ('pliego_' . substr(md5($url), 0, 8) . '.pdf');
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $name) ?: 'pliego.pdf';
        return mb_substr($name, 0, 200);
    }
}
