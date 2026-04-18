<?php

namespace App\Console\Commands;

use App\Actions\SyncOfferDocumentsAction;
use App\Models\Offer;
use Illuminate\Console\Command;

/**
 * Descarga pliegos de la plataforma oficial y los adjunta a la tarea Kanboard.
 *
 * Uso:
 *   php artisan offers:sync-documents --offer=123
 *   php artisan offers:sync-documents --company=1 --limit=50
 *   php artisan offers:sync-documents --pending-only
 */
class SyncOfferDocumentsCommand extends Command
{
    protected $signature = 'offers:sync-documents
        {--offer= : ID de la oferta concreta a procesar}
        {--company= : ID de la empresa (procesa todas sus ofertas con URL+task)}
        {--limit=25 : Limite de ofertas a procesar en modo masivo}
        {--pending-only : Solo ofertas que aun no tienen ningun documento adjuntado}';

    protected $description = 'Descarga pliegos de la plataforma y los adjunta a la tarea Kanboard';

    public function handle(SyncOfferDocumentsAction $action): int
    {
        $offerId   = $this->option('offer');
        $companyId = $this->option('company');
        $limit     = (int) $this->option('limit');
        $pending   = (bool) $this->option('pending-only');

        if ($offerId) {
            $offer = Offer::find($offerId);
            if (! $offer) {
                $this->error("Oferta {$offerId} no encontrada.");
                return self::FAILURE;
            }
            $this->processOne($action, $offer);
            return self::SUCCESS;
        }

        $query = Offer::query()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->whereNotNull('kanboard_task');

        if ($companyId) $query->where('company_id', $companyId);

        if ($pending) {
            $query->whereDoesntHave('documents', fn ($q) => $q->where('status', 'attached'));
        }

        $offers = $query->orderByDesc('created_at')->limit($limit)->get();

        if ($offers->isEmpty()) {
            $this->info('Sin ofertas que procesar.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$offers->count()} ofertas...");
        foreach ($offers as $offer) {
            $this->processOne($action, $offer);
        }

        return self::SUCCESS;
    }

    private function processOne(SyncOfferDocumentsAction $action, Offer $offer): void
    {
        $this->line("- Oferta #{$offer->id} ({$offer->cliente}): " . parse_url($offer->url, PHP_URL_HOST));

        $summary = $action->run($offer);

        if ($summary['provider']) $this->line("    provider: {$summary['provider']}");
        $this->line(sprintf(
            '    found=%d attached=%d dedup=%d failed=%d',
            $summary['found'], $summary['attached'], $summary['skipped_duplicate'], $summary['failed']
        ));

        foreach ($summary['errors'] as $err) {
            $this->warn("    ! {$err}");
        }
    }
}
