<?php

namespace App\Console\Commands;

use App\Services\KanboardSync;
use Illuminate\Console\Command;

class SyncKanboardWorkflows extends Command
{
    protected $signature = 'kanboard:sync-workflows
                            {--company= : ID de empresa a sincronizar (si se omite, todas).}';

    protected $description = 'Reconcilia offers.id_workflow segun la columna real de la tarea en Kanboard.';

    public function handle(KanboardSync $sync): int
    {
        $companyId = $this->option('company');
        $companyId = $companyId ? (int) $companyId : null;

        $this->info($companyId
            ? "Sincronizando ofertas de la empresa {$companyId} con Kanboard..."
            : 'Sincronizando ofertas de todas las empresas con Kanboard...');

        $stats = $sync->pullOffersFromKanboard($companyId);

        $this->table(
            ['Revisadas', 'Fase actualizada', 'Estado actualizado', 'Sin mapeo'],
            [[$stats['checked'], $stats['updated_wf'], $stats['updated_status'], $stats['skipped']]]
        );

        return self::SUCCESS;
    }
}
