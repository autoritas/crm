<?php

namespace App\Console\Commands;

use App\Integrations\TenderSources\Providers\PLACSP\PLACSPWebProvider;
use App\Models\Offer;
use Illuminate\Console\Command;

/**
 * Diagnostico del acceso web a PLACSP con user/password.
 * Uso:
 *   php artisan placsp:web-test --offer=2094
 *
 * Ejecuta login + fetch de detalle + extraccion de URLs de documentos, y
 * reporta paso a paso que ha pasado. No adjunta nada en Kanboard — es solo
 * diagnostico.
 */
class PlacspWebTestCommand extends Command
{
    protected $signature = 'placsp:web-test {--offer= : ID de la oferta a probar}';
    protected $description = 'Diagnostica el login + scraping web de PLACSP con user/password';

    public function handle(): int
    {
        $this->line('');
        $this->info('PLACSP web test (user/password)');
        $this->line(str_repeat('-', 60));

        $user   = config('services.placsp.user');
        $pass   = config('services.placsp.password');
        $cookie = config('services.placsp.cookie');
        $this->line('  PLACSP_USER:     ' . ($user ?: '(vacio)'));
        $this->line('  PLACSP_PASSWORD: ' . ($pass ? '(definida, ' . strlen($pass) . ' chars)' : '(vacia)'));
        $this->line('  PLACSP_COOKIE:   ' . ($cookie ? '(definida, ' . strlen($cookie) . ' chars)' : '(vacia)'));

        if (! $cookie && (! $user || ! $pass)) {
            $this->error('  → Define PLACSP_COOKIE o bien PLACSP_USER + PLACSP_PASSWORD en .env');
            return self::FAILURE;
        }

        if ($cookie) {
            $this->line('  modo:            cookie (atajo rapido)');
        } else {
            $this->line('  modo:            login automatico (user/password)');
        }

        $offerId = $this->option('offer');
        if (! $offerId) {
            $this->error('  → Pasa --offer=<ID>');
            return self::FAILURE;
        }

        $offer = Offer::find($offerId);
        if (! $offer) {
            $this->error("  → Oferta {$offerId} no encontrada");
            return self::FAILURE;
        }

        $this->line('  oferta:          ' . $offer->cliente);
        $this->line('  url:             ' . $offer->url);
        $this->line('');

        $provider = new PLACSPWebProvider();
        if (! $provider->supports($offer->url)) {
            $this->error('  → El provider no soporta esta URL o faltan credenciales');
            return self::FAILURE;
        }

        try {
            $docs = $provider->fetchDocuments($offer);
        } catch (\Throwable $e) {
            $this->error('  → Excepcion: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('');
        $this->info('Resultado: ' . count($docs) . ' documento(s)');
        foreach ($docs as $d) {
            $this->line(sprintf('  - %s  (%s bytes, %s)',
                $d->filename,
                number_format($d->bytes(), 0, '.', '.'),
                $d->mime ?? '?'
            ));
            $this->line('      ' . $d->sourceUrl);
        }

        if (empty($docs)) {
            $this->warn('');
            $this->warn('0 documentos. Puede ser:');
            $this->warn('  - Login OK pero el detalle no muestra pliegos aun.');
            $this->warn('  - Login OK pero el HTML cambio y nuestro XPath no los ve.');
            $this->warn('  - Login silenciosamente fallido (PLACSP ignora user/pass malo).');
            $this->warn('Revisa `storage/logs/laravel.log` — el provider graba la traza.');
        }

        return self::SUCCESS;
    }
}
