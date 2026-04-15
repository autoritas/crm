<?php

namespace App\Filament\Pages;

use App\Models\CompanyKanboardColumn;
use App\Models\Offer;
use App\Models\OfferStatus;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class Indicadores extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Indicadores';

    protected static ?string $title = 'Indicadores';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.indicadores-home';

    public string $activeTab = 'home';
    public string $pendientesFilter = 'all';
    public string $ofertasTimeFilter = 'all';
    public string $ofertasStatusFilter = 'all';

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

        // Obtener columna Kanboard de cada oferta
        $columns = CompanyKanboardColumn::where('company_id', $companyId)
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

        $result = [];
        foreach ($offers as $offer) {
            $kbPhase = $taskColumnMap[(int) $offer->kanboard_task] ?? 'SIN ASIGNAR';

            if ($this->pendientesFilter !== 'all' && $kbPhase !== $this->pendientesFilter) {
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
                'codigo' => $offer->codigo_proyecto,
            ];
        }

        return $result;
    }

    public function getKanboardPhases(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $phases = CompanyKanboardColumn::where('company_id', $companyId)
            ->where('name', '!=', 'GANADO')
            ->orderBy('position')
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

    public function getOfertasCards(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $year = now()->year;

        $ganadoId = OfferStatus::where('company_id', $companyId)->where('status', 'Ganado')->value('id');
        $perdidoId = OfferStatus::where('company_id', $companyId)->where('status', 'Perdido')->value('id');
        $pendienteId = OfferStatus::where('company_id', $companyId)->where('is_default_filter', true)->value('id');

        $statusMap = [
            'ganadas' => $ganadoId,
            'pendientes' => $pendienteId,
            'perdidas' => $perdidoId,
        ];

        $query = Offer::where('company_id', $companyId)
            ->whereNotNull('fecha_presentacion')
            ->with(['offerStatus', 'offerType']);

        // Filtro tiempo
        if ($this->ofertasTimeFilter === '12m') {
            $query->where('fecha_presentacion', '>=', now()->subMonths(12)->startOfMonth());
        } elseif ($this->ofertasTimeFilter === 'year') {
            $query->whereYear('fecha_presentacion', $year);
        }

        // Filtro estado
        if ($this->ofertasStatusFilter !== 'all' && isset($statusMap[$this->ofertasStatusFilter])) {
            $query->where('id_offer_status', $statusMap[$this->ofertasStatusFilter]);
        } else {
            $query->whereIn('id_offer_status', array_filter(array_values($statusMap)));
        }

        return $query->orderBy('fecha_presentacion', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'cliente' => $o->cliente,
                'objeto' => $o->objeto,
                'presupuesto' => $o->importe_licitacion,
                'fecha_presentacion' => $o->fecha_presentacion,
                'url' => $o->url,
                'tipo' => $o->offerType?->name,
                'status' => $o->offerStatus?->status ?? 'Sin estado',
                'status_color' => $o->offerStatus?->color ?? '#6b7280',
                'codigo' => $o->codigo_proyecto,
            ])
            ->toArray();
    }

    public function getOfertasStatusColors(): array
    {
        return [
            'ganadas' => '#22c55e',
            'pendientes' => '#f59e0b',
            'perdidas' => '#ef4444',
        ];
    }
}
