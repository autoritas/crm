<?php

namespace App\Integrations\TenderSources\Providers\PLACSP;

use App\Integrations\TenderSources\Contracts\TenderSourceProvider;
use App\Integrations\TenderSources\DownloadedDocument;
use App\Models\Offer;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Log;

/**
 * Provider alternativo para PLACSP que accede al portal web con user/password
 * (no con certificado digital). Pensado como fallback mientras el cert no
 * esta autorizado para sindicacion.
 *
 * Flujo:
 *   1. GET home → coge cookies de sesion.
 *   2. Busca el formulario de login en el HTML y calcula el POST.
 *   3. POST con PLACSP_USER + PLACSP_PASSWORD → sesion autenticada.
 *   4. GET de la URL de la oferta con la misma CookieJar → HTML con enlaces
 *      reales a los PDFs (lo que vemos en Ctrl+U logueados).
 *   5. Parsea enlaces a PDFs y los descarga con la misma sesion.
 *
 * Si falla el login (cambio de maqueta, credenciales malas, captcha...) lanza
 * RuntimeException y el orquestador lo recoge sin romper el flujo.
 */
class PLACSPWebProvider implements TenderSourceProvider
{
    /** Mismos hosts que el provider de mTLS. */
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

    private const USER_AGENT         = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const HOME_URL           = 'https://contrataciondelestado.es/wps/portal/plataforma';
    private const MAX_BYTES_PER_FILE = 50 * 1024 * 1024; // 50 MB
    private const HTTP_TIMEOUT       = 45;

    public function id(): string    { return 'PLACSP_WEB'; }
    public function label(): string { return 'PLACSP (acceso web con user/password)'; }

    public function supports(string $tenderUrl): bool
    {
        if (! $this->hasCredentials() && ! $this->hasCookie()) return false;
        $host = parse_url($tenderUrl, PHP_URL_HOST) ?: '';
        return in_array(strtolower($host), self::SUPPORTED_HOSTS, true);
    }

    public function fetchDocuments(Offer $offer): array
    {
        $url = $offer->url ?: '';
        if (! $url || ! $this->supports($url)) {
            throw new \RuntimeException("PLACSP_WEB: URL no soportada o credenciales sin configurar");
        }

        $jar    = new CookieJar();
        $client = $this->makeClient($jar);

        // Camino rapido: si hay PLACSP_COOKIE, cargamos la sesion directamente.
        // Si no, intentamos login con user/password (heuristica, puede fallar).
        if ($this->hasCookie()) {
            $this->loadCookieFromConfig($jar);
        } else {
            $this->login($client, $jar);
        }

        $html  = $this->fetchDetailPage($client, $url);
        $links = $this->extractDocumentLinks($html, $url);

        if (empty($links)) {
            Log::info('PLACSP_WEB: HTML sin enlaces a pliegos (sesion expirada o pagina inesperada)', [
                'offer_id' => $offer->id, 'url' => $url, 'html_bytes' => strlen($html),
            ]);
            return [];
        }

        $docs = [];
        foreach ($links as $link) {
            try {
                $docs[] = $this->downloadDocument($client, $link);
            } catch (\Throwable $e) {
                Log::warning('PLACSP_WEB: fallo descargando pliego', [
                    'offer_id' => $offer->id, 'link' => $link, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $docs;
    }

    // ---- login flow ------------------------------------------------------

    private function hasCredentials(): bool
    {
        return (bool) config('services.placsp.user')
            && (bool) config('services.placsp.password');
    }

    private function hasCookie(): bool
    {
        return $this->resolveCookieString() !== '';
    }

    /**
     * Obtiene la cookie desde PLACSP_COOKIE o, si esta vacia, desde el
     * fichero PLACSP_COOKIE_FILE. Esto permite meter cookies muy largas
     * sin pelearse con el parser de phpdotenv.
     */
    private function resolveCookieString(): string
    {
        $inline = (string) config('services.placsp.cookie');
        if ($inline !== '') return trim($inline);

        $path = (string) config('services.placsp.cookie_file');
        if ($path !== '' && is_file($path) && is_readable($path)) {
            $contents = trim(file_get_contents($path) ?: '');
            return $contents;
        }
        return '';
    }

    /**
     * Parsea la cookie cruda (`name1=val1; name2=val2; ...`) y la mete en
     * la CookieJar asociada al dominio de PLACSP.
     */
    private function loadCookieFromConfig(CookieJar $jar): void
    {
        $raw = $this->resolveCookieString();
        foreach (explode(';', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') continue;
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = trim($name);
            $value = trim($value);
            if ($name === '') continue;

            $jar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Name'     => $name,
                'Value'    => $value,
                'Domain'   => 'contrataciondelestado.es',
                'Path'     => '/',
                'Secure'   => true,
                'HttpOnly' => false,
            ]));
        }
    }

    private function makeClient(CookieJar $jar): Client
    {
        return new Client([
            'cookies'         => $jar,
            'timeout'         => self::HTTP_TIMEOUT,
            'connect_timeout' => 10,
            'allow_redirects' => [
                'max'       => 10,
                'strict'    => false,
                'referer'   => true,
                'protocols' => ['http', 'https'],
            ],
            'headers' => [
                'User-Agent'      => self::USER_AGENT,
                'Accept-Language' => 'es-ES,es;q=0.9',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'http_errors' => false,
        ]);
    }

    /**
     * Login heuristico:
     *   1. GET home para obtener JSESSIONID y el HTML que contiene el form.
     *   2. Busca el primer <form> con un input type=password.
     *   3. Serializa todos los inputs del form, mete user/password por nombres
     *      tipicos y hace POST.
     *   4. Verifica que estamos logueados revisando cookies y/o HTML.
     */
    private function login(Client $client, CookieJar $jar): void
    {
        $user = config('services.placsp.user');
        $pass = config('services.placsp.password');

        $home = $client->get(self::HOME_URL);
        if ($home->getStatusCode() >= 400) {
            throw new \RuntimeException("PLACSP_WEB: no pude cargar la home (HTTP {$home->getStatusCode()})");
        }
        $homeHtml = (string) $home->getBody();
        $homeUri  = (string) ($home->getHeaderLine('Content-Location') ?: self::HOME_URL);

        $form = $this->locateLoginForm($homeHtml);
        if (! $form) {
            throw new \RuntimeException(
                'PLACSP_WEB: no encontre el formulario de login en la home. ' .
                'Puede que PLACSP haya cambiado la maqueta o que hayas llegado ya logueado.'
            );
        }

        // Mete user/pass en los campos detectados.
        $fields                  = $form['fields'];
        $fields[$form['user']]   = $user;
        $fields[$form['pass']]   = $pass;

        $action = $this->absolutize($form['action'], $homeUri);

        $resp = $client->post($action, [
            'form_params' => $fields,
            'headers'     => ['Referer' => $homeUri],
        ]);

        if ($resp->getStatusCode() >= 400) {
            throw new \RuntimeException("PLACSP_WEB: login devolvio HTTP {$resp->getStatusCode()}");
        }

        // Heuristica: si el HTML de respuesta sigue teniendo un form con
        // input type=password, el login no entro (credenciales malas o
        // bloqueo por captcha).
        $body = (string) $resp->getBody();
        if ($this->locateLoginForm($body)) {
            throw new \RuntimeException(
                'PLACSP_WEB: el login no parece haber entrado. Credenciales incorrectas, ' .
                'captcha, o cambio de maqueta. Revisa PLACSP_USER / PLACSP_PASSWORD en .env.'
            );
        }
    }

    /**
     * Busca el <form> con input[type=password] en el HTML y devuelve:
     *   ['action' => '...', 'user' => 'nombreCampoUser',
     *    'pass'  => 'nombreCampoPass', 'fields' => [hidden+resto]]
     *
     * @return ?array{action:string, user:string, pass:string, fields:array<string,string>}
     */
    private function locateLoginForm(string $html): ?array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $forms = $xpath->query('//form[.//input[@type="password"]]');
        if (! $forms || $forms->length === 0) return null;

        /** @var \DOMElement $form */
        $form   = $forms->item(0);
        $action = trim($form->getAttribute('action'));
        if ($action === '') $action = self::HOME_URL;

        $fields = [];
        $userField = null;
        $passField = null;

        foreach ($xpath->query('.//input', $form) ?: [] as $input) {
            /** @var \DOMElement $input */
            $name = $input->getAttribute('name');
            if ($name === '') continue;

            $type  = strtolower($input->getAttribute('type') ?: 'text');
            $value = $input->getAttribute('value');

            if ($type === 'password') {
                $passField = $name;
                continue;
            }
            if ($type === 'submit' || $type === 'button' || $type === 'image') {
                continue;
            }
            if (! $userField && ($type === 'text' || $type === 'email' || $type === '')
                && $this->looksLikeUserField($name)) {
                $userField = $name;
            }

            $fields[$name] = $value;
        }

        // Fallback: si no detectamos user por heuristica, cogemos el primer
        // campo visible que no sea hidden.
        if (! $userField) {
            foreach ($xpath->query('.//input[@type="text" or @type="email" or not(@type)]', $form) ?: [] as $input) {
                /** @var \DOMElement $input */
                $name = $input->getAttribute('name');
                if ($name !== '') { $userField = $name; break; }
            }
        }

        if (! $userField || ! $passField) return null;

        return [
            'action' => $action,
            'user'   => $userField,
            'pass'   => $passField,
            'fields' => $fields,
        ];
    }

    private function looksLikeUserField(string $name): bool
    {
        $n = strtolower($name);
        foreach (['user', 'login', 'email', 'usuario', 'correo'] as $hint) {
            if (str_contains($n, $hint)) return true;
        }
        return false;
    }

    // ---- detail + document links ----------------------------------------

    private function fetchDetailPage(Client $client, string $url): string
    {
        $resp = $client->get($url);
        if ($resp->getStatusCode() >= 400) {
            throw new \RuntimeException("PLACSP_WEB: HTTP {$resp->getStatusCode()} pidiendo detalle");
        }
        return (string) $resp->getBody();
    }

    /**
     * Extrae URLs a PDFs del HTML del detalle.
     *
     * En PLACSP los documentos estan en la tabla `myTablaDetalleVISUOE`, con 3
     * enlaces por fila (HTML/XML/PDF), del tipo:
     *   https://contrataciondelestado.es/FileSystem/servlet/GetDocumentByIdServlet?cifrado=X&DocumentIdParam=Y
     *
     * Nos quedamos solo con los que son PDF (detectados por el <img alt="Documento pdf">
     * dentro del <a>) y descartamos los iconos HTML/XML (son alternativas del mismo doc)
     * y los "sellos de tiempo" (que son JSF y solo valen para verificar firma).
     *
     * Tambien evitamos la tabla `myTablaDetalleVISUOE_Anulados` (documentos retirados).
     *
     * @return string[]
     */
    private function extractDocumentLinks(string $html, string $baseUrl): array
    {
        $urls = [];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Caso PLACSP tipico: enlaces con icono PDF dentro de la tabla activa.
        $placspQuery = '//table[@id="myTablaDetalleVISUOE"]//a[.//img[@alt="Documento pdf"]]';
        foreach ($xpath->query($placspQuery) ?: [] as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '') continue;
            $urls[$this->absolutize($href, $baseUrl)] = true;
        }

        // Fallback generico: cualquier <a> directo a .pdf/zip/doc o GetDocumentByIdServlet.
        $genericQuery = '//a[
            contains(@href, ".pdf") or contains(@href, ".PDF") or
            contains(@href, ".zip") or contains(@href, ".ZIP") or
            contains(@href, ".doc") or contains(@href, ".docx") or
            contains(@href, "/wps/wcm/connect/") or
            contains(@href, "GetDocumentByIdServlet")
        ]';
        foreach ($xpath->query($genericQuery) ?: [] as $node) {
            /** @var \DOMElement $node */
            $href = trim($node->getAttribute('href'));
            if ($href === '') continue;
            // Si el enlace tiene un <img> cuyo alt NO es PDF, lo ignoramos (es HTML/XML).
            foreach ($node->getElementsByTagName('img') as $img) {
                $alt = $img->getAttribute('alt');
                if ($alt !== '' && stripos($alt, 'pdf') === false
                    && (stripos($alt, 'html') !== false || stripos($alt, 'xml') !== false
                        || stripos($alt, 'sello') !== false)) {
                    continue 2;
                }
            }
            $abs = $this->absolutize($href, $baseUrl);
            if ($this->looksLikeDocumentUrl($abs)) {
                $urls[$abs] = true;
            }
        }

        // Regex global para PDFs embebidos en JS (no tipico en PLACSP pero por higiene).
        preg_match_all(
            '#https?://[^\s"\'<>]+?\.(?:pdf|zip|docx?|xlsx?|rtf)(?:\?[^\s"\'<>]*)?#i',
            $html,
            $m
        );
        foreach ($m[0] ?? [] as $u) {
            $urls[$u] = true;
        }

        return array_keys($urls);
    }

    private function looksLikeDocumentUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) return false;
        return preg_match('#\.(pdf|zip|docx?|xlsx?|rtf)(\?|$)#i', $url)
            || str_contains($url, '/wps/wcm/connect/')
            || str_contains($url, 'GetDocumentByIdServlet');
    }

    private function absolutize(string $href, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $href)) return $href;

        $base   = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host   = $base['host']   ?? 'contrataciondelestado.es';

        if (str_starts_with($href, '//')) return $scheme . ':' . $href;
        if (str_starts_with($href, '/'))  return $scheme . '://' . $host . $href;

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
        return $scheme . '://' . $host . $basePath . '/' . $href;
    }

    // ---- download --------------------------------------------------------

    private function downloadDocument(Client $client, string $url): DownloadedDocument
    {
        $resp = $client->get($url, [
            'headers' => ['Accept' => '*/*'],
        ]);

        if ($resp->getStatusCode() >= 400) {
            throw new \RuntimeException("HTTP {$resp->getStatusCode()} descargando {$url}");
        }

        $bytes = (string) $resp->getBody();
        if (strlen($bytes) > self::MAX_BYTES_PER_FILE) {
            throw new \RuntimeException('Fichero demasiado grande: ' . strlen($bytes) . ' bytes');
        }

        $cd   = $resp->getHeaderLine('Content-Disposition');
        $mime = $resp->getHeaderLine('Content-Type') ?: null;

        return new DownloadedDocument(
            sourceUrl: $url,
            filename:  $this->deriveFilename($url, $cd),
            content:   $bytes,
            mime:      $mime,
        );
    }

    private function deriveFilename(string $url, string $cd): string
    {
        // 1) Content-Disposition es lo mas fiable (PLACSP lo manda bien).
        if ($cd !== '' && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $cd, $m)) {
            return $this->sanitizeFilename(rawurldecode(trim($m[1])));
        }

        // 2) Si la URL es el servlet de PLACSP, basename es "GetDocumentByIdServlet"
        //    y no sirve. Generamos un nombre basado en hash del DocumentIdParam.
        if (str_contains($url, 'GetDocumentByIdServlet')) {
            parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $q);
            $seed = $q['DocumentIdParam'] ?? $url;
            return 'pliego_' . substr(md5($seed), 0, 8) . '.pdf';
        }

        // 3) Ultima vía: basename de la URL. Si no trae extension, asumimos PDF.
        $base = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if ($base === '') return 'pliego_' . substr(md5($url), 0, 8) . '.pdf';
        if (! preg_match('/\.[a-z0-9]{2,5}$/i', $base)) $base .= '.pdf';
        return $this->sanitizeFilename($base);
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $name) ?: 'pliego.pdf';
        return mb_substr($name, 0, 200);
    }
}
