<?php

namespace App\Filament\Pages;

use App\Http\Controllers\CribadoController;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\ScreeningReason;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Cribado extends Page
{
    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Cribado';

    protected static ?string $title = 'Cribado IA';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.cribado';

    public array $selectedItems = [];

    private function controller(): CribadoController
    {
        return app(CribadoController::class);
    }

    public function getGroups(): array
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = InfonaliaStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        $items = InfonaliaData::where('company_id', $companyId)
            ->where('id_decision', $pendienteId)
            ->whereNotNull('id_ia_decision')
            ->with(['iaDecision', 'decision'])
            ->orderBy('id_ia_decision')
            ->orderByDesc('presupuesto')
            ->get();

        $groups = [];
        foreach ($items as $item) {
            $groupKey = $item->id_ia_decision ?? 0;
            $groupName = $item->iaDecision?->status ?? 'Sin clasificar';
            $groupColor = $item->iaDecision?->color ?? '#6b7280';

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'name' => $groupName,
                    'color' => $groupColor,
                    'items' => [],
                ];
            }
            $groups[$groupKey]['items'][] = $item;
        }

        return $groups;
    }

    public function confirmAll(): void
    {
        $companyId = (int) session('current_company_id', 1);
        $result = $this->controller()->confirmAll($companyId);

        $this->selectedItems = [];
        Notification::make()->title("Confirmados {$result['count']} items")->success()->send();
    }

    public function confirmSelected(): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()->title('No hay items seleccionados')->warning()->send();
            return;
        }

        $result = $this->controller()->confirmSelected($this->selectedItems);

        $this->selectedItems = [];
        Notification::make()->title("Confirmados {$result['count']} items seleccionados")->success()->send();
    }

    public function bulkDecision(int $newDecision): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()->title('No hay items seleccionados')->warning()->send();
            return;
        }

        $result = $this->controller()->bulkDecision($this->selectedItems, $newDecision);

        $this->selectedItems = [];
        Notification::make()->title("Cambiados {$result['count']} items a: {$result['status']}")->success()->send();
    }

    public function confirm(int $id): void
    {
        $result = $this->controller()->confirm($id);
        Notification::make()->title($result['message'])->success()->send();
    }

    public function override(int $id, int $newDecision, ?int $reasonId, ?string $comment): void
    {
        $result = $this->controller()->override($id, $newDecision, $reasonId, $comment);
        Notification::make()->title($result['message'])->success()->send();
    }

    /**
     * Accion unitaria "pulgar arriba = ofertar" / "pulgar abajo = descartar".
     *
     * @param  int     $id        id del InfonaliaData
     * @param  string  $direction 'ofertar' | 'descartar'
     * @param  ?int    $reasonId  obligatorio si el controller lo pide
     * @param  ?string $comment
     */
    public function decide(int $id, string $direction, ?int $reasonId = null, ?string $comment = null): void
    {
        try {
            $result = $this->controller()->decide($id, $direction, $reasonId, $comment);
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
            return;
        }

        $type = $result['status'] === 'offer_created' ? 'success' : ($result['status'] === 'discarded' ? 'warning' : 'info');
        Notification::make()->title($result['message'])->{$type}()->send();
    }

    /**
     * Version masiva del pulgar. Aplica `direction` a todos los seleccionados.
     * Si alguno requiere motivo y no se pasa, el controller avisa y el
     * frontend abrira el modal de motivo con el conteo.
     */
    public function bulkDecide(string $direction, ?int $reasonId = null, ?string $comment = null): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()->title('No hay items seleccionados')->warning()->send();
            return;
        }

        try {
            $result = $this->controller()->bulkDecide($this->selectedItems, $direction, $reasonId, $comment);
        } catch (\Throwable $e) {
            Notification::make()->title('Error')->body($e->getMessage())->danger()->send();
            return;
        }

        if ($result['status'] === 'needs_reason') {
            // Emite un evento que el frontend captura para abrir el modal.
            $this->dispatch('bulk-needs-reason',
                direction: $direction,
                count: $result['reason_needed_count'],
            );
            return;
        }

        $this->selectedItems = [];
        $type = $direction === 'ofertar' ? 'success' : 'warning';
        Notification::make()->title($result['message'])->{$type}()->send();
    }

    /**
     * Mapa id_ia_decision → 'ofertar' | 'descartar' | 'revision' para que
     * el frontend sepa cuando pedir motivo sin ir al servidor.
     */
    public function getIaDirectionMap(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $statuses = InfonaliaStatus::where('company_id', $companyId)->get();

        $map = [];
        foreach ($statuses as $s) {
            if (\App\Http\Controllers\CribadoController::isReviewStatus($s->status)) {
                $map[$s->id] = 'revision';
            } elseif ($s->generates_offer) {
                $map[$s->id] = 'ofertar';
            } else {
                $map[$s->id] = 'descartar';
            }
        }
        return $map;
    }

    public function getStatusOptions(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return InfonaliaStatus::where('company_id', $companyId)->pluck('status', 'id')->toArray();
    }

    /**
     * Mapa de status_id → tipo de transicion para el frontend.
     * 'ofertar' = genera oferta (motivo positivo requerido)
     * 'descartar' = descarte (motivo negativo requerido)
     * 'dudoso' / 'pendiente' = sin motivo requerido
     */
    public function getStatusTypes(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $statuses = InfonaliaStatus::where('company_id', $companyId)->get();
        $types = [];

        foreach ($statuses as $s) {
            if ($s->generates_offer) {
                $types[$s->id] = 'ofertar';
            } elseif ($s->is_default_filter) {
                $types[$s->id] = 'pendiente';
            } elseif (CribadoController::isReviewStatus($s->status)) {
                $types[$s->id] = 'dudoso';
            } else {
                $types[$s->id] = 'descartar';
            }
        }

        return $types;
    }

    public function getNegativeReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('company_id', $companyId)
            ->where('type', 'negative')
            ->pluck('reason', 'id')->toArray();
    }

    public function getPositiveReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('company_id', $companyId)
            ->where('type', 'positive')
            ->pluck('reason', 'id')->toArray();
    }
}
