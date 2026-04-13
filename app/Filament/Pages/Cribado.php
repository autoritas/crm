<?php

namespace App\Filament\Pages;

use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\ScreeningReason;
use Filament\Forms;
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

    public function getGroups(): array
    {
        $companyId = (int) session('current_company_id', 1);

        // Obtener el status "Pendiente" para filtrar decision = Pendiente
        $pendienteId = InfonaliaStatus::where('id_company', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        // Agrupar por ia_decision
        $statuses = InfonaliaStatus::where('id_company', $companyId)->get()->keyBy('id');

        $items = InfonaliaData::where('id_company', $companyId)
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

    public function confirm(int $id): void
    {
        $record = InfonaliaData::findOrFail($id);
        $record->update([
            'id_decision' => $record->id_ia_decision,
            'revisado_humano' => true,
            'revisado_fecha' => now(),
        ]);

        Notification::make()->title('Confirmado: ' . ($record->iaDecision?->status ?? ''))->success()->send();
    }

    public function getStatusOptions(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return InfonaliaStatus::where('id_company', $companyId)->pluck('status', 'id')->toArray();
    }

    public function getNegativeReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('id_company', $companyId)
            ->where('type', 'negative')
            ->pluck('reason', 'id')->toArray();
    }

    public function getPositiveReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('id_company', $companyId)
            ->where('type', 'positive')
            ->pluck('reason', 'id')->toArray();
    }

    public function override(int $id, int $newDecision, ?int $reasonId, ?string $comment): void
    {
        $record = InfonaliaData::findOrFail($id);
        $record->update([
            'id_decision' => $newDecision,
            'revisado_humano' => true,
            'revisado_fecha' => now(),
            'id_screening_reason' => $reasonId,
            'screening_comment' => $comment,
        ]);

        $statusName = InfonaliaStatus::find($newDecision)?->status ?? '';
        Notification::make()->title("Cambiado a: {$statusName}")->success()->send();
    }
}
