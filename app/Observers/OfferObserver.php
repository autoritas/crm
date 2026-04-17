<?php

namespace App\Observers;

use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferWorkflow;
use App\Services\KanboardSync;
use Illuminate\Support\Facades\Log;

/**
 * Observa cambios en Offer para propagar:
 *  - cambios de fase (id_workflow) -> moveTaskPosition en Kanboard.
 *  - cambios de estado (id_offer_status) -> close/open de la tarea en
 *    Kanboard segun la tabla de fases:
 *      * si el nuevo estado figura como `closed_offer_status_id` de
 *        alguna fase de la empresa -> closeTask.
 *      * si el nuevo estado es el `is_default_filter` (Pendiente) y la
 *        tarea estaba cerrada -> openTask.
 */
class OfferObserver
{
    public function __construct(private KanboardSync $sync) {}

    public function updated(Offer $offer): void
    {
        if (!$offer->kanboard_task) return;

        if ($offer->wasChanged('id_workflow')) {
            $err = $this->sync->pushOfferToKanboard($offer);
            if ($err) {
                Log::warning('OfferObserver: pushOfferToKanboard fallo', [
                    'offer_id' => $offer->id,
                    'reason'   => $err,
                ]);
            }
        }

        if ($offer->wasChanged('id_offer_status')) {
            $this->applyStatusToKanboard($offer);
        }
    }

    private function applyStatusToKanboard(Offer $offer): void
    {
        $taskId = (int) $offer->kanboard_task;
        if (!$taskId || !$offer->id_offer_status) return;

        // Conjunto de estados "de cierre" definidos para la empresa.
        $closedStatuses = OfferWorkflow::where('company_id', $offer->company_id)
            ->whereNotNull('closed_offer_status_id')
            ->pluck('closed_offer_status_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();

        if (in_array((int) $offer->id_offer_status, $closedStatuses, true)) {
            $this->sync->closeTask($taskId);
            return;
        }

        // Estado "pendiente" (is_default_filter) -> reabrir tarea.
        $defaultFilterId = OfferStatus::where('company_id', $offer->company_id)
            ->where('is_default_filter', true)
            ->value('id');

        if ($defaultFilterId && (int) $offer->id_offer_status === (int) $defaultFilterId) {
            $this->sync->openTask($taskId);
        }
    }
}
