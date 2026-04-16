<?php

namespace App\Services;

use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferStatus;
use Carbon\Carbon;

class KpiService
{
    private int $companyId;

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function pendientes(): array
    {
        $pendienteId = OfferStatus::where('company_id', $this->companyId)
            ->where('is_default_filter', true)
            ->value('id');

        // Ofertas pendientes con kanboard_task
        $offers = Offer::where('company_id', $this->companyId)
            ->where('id_offer_status', $pendienteId)
            ->whereNotNull('kanboard_task')
            ->select('id', 'kanboard_task', 'importe_licitacion')
            ->get();

        // Mapeo columnas Kanboard de la empresa (desde offer_workflows)
        $columns = \App\Models\OfferWorkflow::where('company_id', $this->companyId)
            ->whereNotNull('kanboard_column_id')
            ->pluck('name', 'kanboard_column_id')
            ->toArray();

        // Consultar columna actual de cada tarea en Kanboard
        $taskColumnMap = [];
        if ($offers->isNotEmpty()) {
            $taskIds = $offers->pluck('kanboard_task')->map(fn ($v) => (int) $v)->filter()->toArray();
            if (!empty($taskIds)) {
                $placeholders = implode(',', $taskIds);
                $tasks = \Illuminate\Support\Facades\DB::connection('kanboard')
                    ->select("SELECT id, column_id FROM tasks WHERE id IN ({$placeholders})");
                foreach ($tasks as $t) {
                    $taskColumnMap[$t->id] = $columns[$t->column_id] ?? 'DESCONOCIDO';
                }
            }
        }

        // Agrupar ofertas por columna Kanboard
        $phases = ['PROSPECTS' => [], 'OFERTAR' => [], 'EN CURSO' => [], 'EN DECISION' => []];

        foreach ($offers as $offer) {
            $colName = $taskColumnMap[(int) $offer->kanboard_task] ?? 'DESCONOCIDO';
            if (!isset($phases[$colName])) $phases[$colName] = [];
            $phases[$colName][] = $offer;
        }

        // Ofertas pendientes SIN kanboard_task
        $sinKanboard = Offer::where('company_id', $this->companyId)
            ->where('id_offer_status', $pendienteId)
            ->where(function ($q) { $q->whereNull('kanboard_task')->orWhere('kanboard_task', ''); })
            ->select('id', 'importe_licitacion')
            ->get();
        if ($sinKanboard->isNotEmpty()) {
            $phases['PROSPECTS'] = array_merge($phases['PROSPECTS'], $sinKanboard->all());
        }

        // Calcular totales por fase
        $result = ['phases' => [], 'total_count' => 0, 'total_importe' => 0];
        $colors = [
            'PROSPECTS' => '#94a3b8',
            'OFERTAR' => '#3b82f6',
            'EN CURSO' => '#f59e0b',
            'EN DECISION' => '#ef4444',
        ];

        foreach ($phases as $name => $phaseOffers) {
            $count = count($phaseOffers);
            $importe = collect($phaseOffers)->sum('importe_licitacion');
            $result['phases'][$name] = [
                'count' => $count,
                'importe' => (float) $importe,
                'color' => $colors[$name] ?? '#6b7280',
            ];
            $result['total_count'] += $count;
            $result['total_importe'] += $importe;
        }

        // Porcentajes
        foreach ($result['phases'] as $name => &$phase) {
            $phase['pct_count'] = $result['total_count'] > 0 ? round($phase['count'] / $result['total_count'] * 100, 1) : 0;
            $phase['pct_importe'] = $result['total_importe'] > 0 ? round($phase['importe'] / $result['total_importe'] * 100, 1) : 0;
        }

        return $result;
    }

    public function ofertas(): array
    {
        $year = Carbon::now()->year;
        $last12 = Carbon::now()->subMonths(12)->startOfMonth();

        $ganadoId = OfferStatus::where('company_id', $this->companyId)->where('status', 'Ganado')->value('id');
        $perdidoId = OfferStatus::where('company_id', $this->companyId)->where('status', 'Perdido')->value('id');
        $pendienteId = OfferStatus::where('company_id', $this->companyId)->where('is_default_filter', true)->value('id');

        $calc = function ($query) use ($ganadoId, $perdidoId, $pendienteId) {
            $base = clone $query;

            $ganCount = (clone $base)->where('id_offer_status', $ganadoId)->count();
            $perCount = (clone $base)->where('id_offer_status', $perdidoId)->count();
            $penCount = (clone $base)->where('id_offer_status', $pendienteId)->count();
            $ganImporte = (float) (clone $base)->where('id_offer_status', $ganadoId)->sum('importe_licitacion');
            $perImporte = (float) (clone $base)->where('id_offer_status', $perdidoId)->sum('importe_licitacion');
            $penImporte = (float) (clone $base)->where('id_offer_status', $pendienteId)->sum('importe_licitacion');

            $totalCount = $ganCount + $perCount + $penCount;
            $totalImporte = $ganImporte + $perImporte + $penImporte;

            return [
                'gan_count' => $ganCount,
                'per_count' => $perCount,
                'pen_count' => $penCount,
                'gan_importe' => $ganImporte,
                'per_importe' => $perImporte,
                'pen_importe' => $penImporte,
                'total_count' => $totalCount,
                'total_importe' => $totalImporte,
                'pct_gan_count' => $totalCount > 0 ? round($ganCount / $totalCount * 100, 1) : 0,
                'pct_per_count' => $totalCount > 0 ? round($perCount / $totalCount * 100, 1) : 0,
                'pct_pen_count' => $totalCount > 0 ? round($penCount / $totalCount * 100, 1) : 0,
                'pct_gan_importe' => $totalImporte > 0 ? round($ganImporte / $totalImporte * 100, 1) : 0,
                'pct_per_importe' => $totalImporte > 0 ? round($perImporte / $totalImporte * 100, 1) : 0,
                'pct_pen_importe' => $totalImporte > 0 ? round($penImporte / $totalImporte * 100, 1) : 0,
            ];
        };

        $base = Offer::where('company_id', $this->companyId)->whereNotNull('fecha_presentacion');

        return [
            'year' => $year,
            'total' => $calc(clone $base),
            'u12' => $calc((clone $base)->where('fecha_presentacion', '>=', $last12)),
            'year_actual' => $calc((clone $base)->whereYear('fecha_presentacion', $year)),
        ];
    }

    public function leads(): array
    {
        // Statuses con id y color
        $statuses = InfonaliaStatus::where('company_id', $this->companyId)
            ->get(['id', 'status', 'color'])
            ->keyBy('id');

        // Últimos 6 trimestres
        $now = Carbon::now();
        $quarters = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = $now->copy()->subQuarters($i)->startOfQuarter();
            $quarters[] = [
                'year' => $d->year,
                'quarter' => $d->quarter,
                'label' => $d->quarter . 'T' . $d->year,
            ];
        }

        // Query agrupada
        $raw = \Illuminate\Support\Facades\DB::table('infonalia_data')
            ->where('company_id', $this->companyId)
            ->whereNotNull('fecha_ingreso')
            ->whereNotNull('id_decision')
            ->selectRaw('YEAR(fecha_ingreso) as yr, QUARTER(fecha_ingreso) as qt, id_decision, COUNT(*) as cnt, COALESCE(SUM(presupuesto), 0) as importe')
            ->groupBy('yr', 'qt', 'id_decision')
            ->get();

        // Organizar datos por trimestre y decision
        $data = [];
        $maxCount = 0;
        $maxImporte = 0;

        foreach ($quarters as $q) {
            $qKey = $q['label'];
            $data[$qKey] = ['label' => $qKey, 'segments' => [], 'total_count' => 0, 'total_importe' => 0];

            foreach ($statuses as $sId => $status) {
                $match = $raw->first(fn ($r) => $r->yr == $q['year'] && $r->qt == $q['quarter'] && $r->id_decision == $sId);
                $cnt = $match ? (int) $match->cnt : 0;
                $imp = $match ? (float) $match->importe : 0;

                $data[$qKey]['segments'][$sId] = [
                    'status' => $status->status,
                    'color' => $status->color,
                    'count' => $cnt,
                    'importe' => $imp,
                ];
                $data[$qKey]['total_count'] += $cnt;
                $data[$qKey]['total_importe'] += $imp;
            }

            $maxCount = max($maxCount, $data[$qKey]['total_count']);
            $maxImporte = max($maxImporte, $data[$qKey]['total_importe']);
        }

        return [
            'quarters' => $data,
            'statuses' => $statuses,
            'max_count' => $maxCount,
            'max_importe' => $maxImporte,
        ];
    }
}
