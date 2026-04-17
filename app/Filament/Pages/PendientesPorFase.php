<?php

namespace App\Filament\Pages;

use App\Models\OfferWorkflow;
use App\Models\Offer;
use App\Models\OfferStatus;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PendientesPorFase extends Page
{
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Pendientes por fase';

    protected static ?string $title = 'Pendientes por fase';

    protected static ?string $slug = 'admin-pendientes-por-fase';

    protected static ?int $navigationSort = 22;

    protected static string $view = 'filament.pages.pendientes-por-fase';

    public string $phaseFilter = 'all';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getKanboardPhases(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $phases = OfferWorkflow::where('company_id', $companyId)
            ->where('name', '!=', 'GANADO')
            ->whereNotNull('kanboard_column_id')
            ->orderBy('sort_order')
            ->pluck('name')
            ->toArray();

        $phases[] = 'SIN ASIGNAR';

        return $phases;
    }

    public function getPhaseColors(): array
    {
        return [
            'PROSPECTS' => '#94a3b8',
            'OFERTAR' => '#3b82f6',
            'EN CURSO' => '#f59e0b',
            'EN DECISION' => '#ef4444',
            'GANADO' => '#22c55e',
            'SIN ASIGNAR' => '#6b7280',
        ];
    }

    public function getPendientesOffers(): array
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        $offers = Offer::where('company_id', $companyId)
            ->where('id_offer_status', $pendienteId)
            ->whereNotNull('fecha_presentacion')
            ->with(['offerType'])
            ->orderBy('fecha_presentacion', 'desc')
            ->get();

        $columns = OfferWorkflow::where('company_id', $companyId)
            ->whereNotNull('kanboard_column_id')
            ->pluck('name', 'kanboard_column_id')
            ->toArray();

        $taskIds = $offers->pluck('kanboard_task')->filter()->map(fn ($v) => (int) $v)->toArray();
        $taskColumnMap = [];
        $taskCategoryMap = [];

        if (!empty($taskIds)) {
            $placeholders = implode(',', $taskIds);
            $tasks = DB::connection('kanboard')
                ->select("SELECT id, column_id, category_id FROM tasks WHERE id IN ({$placeholders})");

            $categoryIds = array_filter(array_map(fn ($t) => (int) $t->category_id, $tasks));
            $categoryNames = [];
            if (!empty($categoryIds)) {
                $catPlaceholders = implode(',', array_unique($categoryIds));
                $cats = DB::connection('kanboard')
                    ->select("SELECT id, name FROM project_has_categories WHERE id IN ({$catPlaceholders})");
                foreach ($cats as $c) {
                    $categoryNames[(int) $c->id] = $c->name;
                }
            }

            foreach ($tasks as $t) {
                $taskColumnMap[$t->id] = $columns[$t->column_id] ?? 'SIN ASIGNAR';
                $taskCategoryMap[$t->id] = $categoryNames[(int) $t->category_id] ?? null;
            }
        }

        $result = [];
        foreach ($offers as $offer) {
            $kbPhase = $taskColumnMap[(int) $offer->kanboard_task] ?? 'SIN ASIGNAR';

            if ($this->phaseFilter !== 'all' && $kbPhase !== $this->phaseFilter) {
                continue;
            }

            $result[] = [
                'id' => $offer->id,
                'cliente' => $offer->cliente,
                'objeto' => $offer->objeto,
                'presupuesto' => $offer->importe_licitacion,
                'fecha_presentacion' => $offer->fecha_presentacion,
                'url' => $offer->url,
                'tipo' => $offer->offerType?->name,
                'kb_phase' => $kbPhase,
                'kb_category' => $taskCategoryMap[(int) $offer->kanboard_task] ?? null,
                'codigo' => $offer->codigo_proyecto,
            ];
        }

        return $result;
    }

    public function getPhaseCounts(): array
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        $offers = Offer::where('company_id', $companyId)
            ->where('id_offer_status', $pendienteId)
            ->whereNotNull('fecha_presentacion')
            ->get();

        $columns = OfferWorkflow::where('company_id', $companyId)
            ->whereNotNull('kanboard_column_id')
            ->pluck('name', 'kanboard_column_id')
            ->toArray();

        $taskIds = $offers->pluck('kanboard_task')->filter()->map(fn ($v) => (int) $v)->toArray();
        $taskColumnMap = [];

        if (!empty($taskIds)) {
            $placeholders = implode(',', $taskIds);
            $tasks = DB::connection('kanboard')
                ->select("SELECT id, column_id FROM tasks WHERE id IN ({$placeholders})");
            foreach ($tasks as $t) {
                $taskColumnMap[$t->id] = $columns[$t->column_id] ?? 'SIN ASIGNAR';
            }
        }

        $counts = [];
        foreach ($offers as $offer) {
            $phase = $taskColumnMap[(int) $offer->kanboard_task] ?? 'SIN ASIGNAR';
            $counts[$phase] = ($counts[$phase] ?? 0) + 1;
        }

        return $counts;
    }
}
