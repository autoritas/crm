<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnostico del certificado digital para PLACSP.
 *
 * Checks:
 *   1. Config valida (cert_path y cert_password).
 *   2. Fichero existe, es legible y tiene permisos razonables.
 *   3. OpenSSL puede abrir el .p12 con la password.
 *   4. Una peticion HTTPS con el cert es aceptada por PLACSP.
 */
class PlacspCertCheckCommand extends Command
{
    protected $signature = 'placsp:cert-check';
    protected $description = 'Diagnostica la configuracion del certificado digital para PLACSP';

    public function handle(): int
    {
        $this->line('');
        $this->info('PLACSP cert check');
        $this->line(str_repeat('-', 60));

        // 1. Config
        $path = config('services.placsp.cert_path');
        $pass = config('services.placsp.cert_password');

        $this->line('  cert_path: ' . ($path ?: '(vacio)'));
        $this->line('  password:  ' . ($pass ? '(definida, ' . strlen($pass) . ' chars)' : '(VACIA)'));

        if (! $path) { $this->error('  → Falta PLACSP_CERT_PATH en .env'); return self::FAILURE; }
        if (! $pass) { $this->error('  → Falta PLACSP_CERT_PASSWORD en .env'); return self::FAILURE; }

        // 2. Fichero
        if (! file_exists($path)) {
            $this->error("  → El fichero {$path} no existe");
            return self::FAILURE;
        }
        if (! is_readable($path)) {
            $this->error("  → El fichero no es legible (permisos)");
            return self::FAILURE;
        }
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $this->line('  permisos:  ' . $perms . (intval($perms, 8) & 0o044 ? ' (⚠ deberia ser 600)' : ' OK'));
        $this->line('  tamano:    ' . filesize($path) . ' bytes');

        if (str_contains($path, '/public/')) {
            $this->error('  → PELIGRO: el cert esta en /public/ (web-servido). Muevelo a storage/app/certs/.');
            return self::FAILURE;
        }

        // 3. OpenSSL puede abrirlo
        $this->line('');
        $this->info('Probando apertura con OpenSSL...');
        $p12 = file_get_contents($path);
        $certs = [];
        if (! openssl_pkcs12_read($p12, $certs, $pass)) {
            $this->error('  → openssl_pkcs12_read fallo. Password incorrecta o p12 corrupto.');
            while ($err = openssl_error_string()) $this->line('    ' . $err);
            return self::FAILURE;
        }
        $this->line('  ✓ p12 abierto correctamente');

        if (isset($certs['cert'])) {
            $parsed = openssl_x509_parse($certs['cert']);
            if ($parsed) {
                $this->line('  subject:   ' . ($parsed['subject']['CN'] ?? '?'));
                $this->line('  issuer:    ' . ($parsed['issuer']['CN'] ?? '?'));
                $this->line('  validTo:   ' . date('Y-m-d H:i', $parsed['validTo_time_t'] ?? 0));
                if (($parsed['validTo_time_t'] ?? 0) < time()) {
                    $this->error('  → Certificado CADUCADO');
                    return self::FAILURE;
                }
            }
        }

        // 4. Peticion HTTPS con mTLS
        $this->line('');
        $this->info('Probando peticion HTTPS con mTLS a PLACSP...');

        $testUrl = 'https://contrataciondelestado.es/sindicacion/sindicacion_643.atom';

        try {
            $resp = Http::withHeaders(['User-Agent' => 'CRM-Autoritas/cert-check'])
                ->timeout(30)
                ->withOptions([
                    'curl' => [
                        CURLOPT_SSLCERT        => $path,
                        CURLOPT_SSLCERTTYPE    => 'P12',
                        CURLOPT_SSLCERTPASSWD  => $pass,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                    ],
                    'allow_redirects' => true,
                ])
                ->get($testUrl);

            $body = $resp->body();
            $this->line('  HTTP: ' . $resp->status() . ' (' . strlen($body) . ' bytes)');

            if (stripos($body, 'certificado') !== false && stripos($body, 'autorizado') !== false) {
                $this->error('  → PLACSP responde: certificado NO autorizado.');
                $this->line('    El certificado es valido, pero la plataforma no lo admite.');
                $this->line('    Suele pasar si no es un cert reconocido (FNMT/Izenpe/...) o no esta dado de alta.');
                return self::FAILURE;
            }

            if (! $resp->ok()) {
                $this->error('  → HTTP ' . $resp->status());
                $this->line('  body (500): ' . substr($body, 0, 500));
                return self::FAILURE;
            }

            if (stripos($body, '<feed') === false && stripos($body, '<?xml') === false) {
                $this->warn('  ? Respuesta 200 pero no parece XML/Atom');
                $this->line('  body (500): ' . substr($body, 0, 500));
            } else {
                $this->line('  ✓ Respuesta XML valida recibida');
            }

            $this->line('');
            $this->info('Todo OK. El certificado esta autorizado en PLACSP.');
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('  → Excepcion: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
