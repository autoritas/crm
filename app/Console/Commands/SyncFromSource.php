<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferFormula;
use App\Models\OfferCompetitor;
use App\Models\OfferCompetitorScore;
use App\Models\ClientAlias;
use App\Models\CompetitorAlias;

class SyncFromSource extends Command
{
    protected $signature = 'sync:from-source {--since=2026-04-13} {--company=all}';
    protected $description = 'Sync new/updated records from source databases (absolute, gestion) to CRM';

    private array $companies = [
        1 => ['connection' => 'source_gestion', 'name' => 'Autoritas'],
        2 => ['connection' => 'source_absolute', 'name' => 'Absolute'],
    ];

    public function handle(): void
    {
        $since = $this->option('since');
        $companyFilter = $this->option('company');

        $this->info("Syncing records updated since: {$since}");

        foreach ($this->companies as $companyId => $config) {
            if ($companyFilter !== 'all' && (int) $companyFilter !== $companyId) continue;

            $this->newLine();
            $this->info("=== {$config['name']} (company {$companyId}) ===");

            try {
                $this->syncInfonalia($companyId, $config['connection'], $since);
                $this->syncOfertas($companyId, $config['connection'], $since);
            } catch (\Exception $e) {
                $this->error("Error syncing {$config['name']}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('=== SYNC COMPLETE ===');
    }

    private function syncInfonalia(int $companyId, string $connection, string $since): void
    {
        $this->info('Syncing InfonaliaData...');

        $statuses = InfonaliaStatus::where('id_company', $companyId)
            ->pluck('id', 'status')
            ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
            ->toArray();
        $defaultDecisionId = $statuses['pendiente'] ?? null;

        // Detectar si tiene columnas IA (Autoritas sí, Absolute no)
        $hasIa = true;
        try {
            DB::connection($connection)->selectOne("SELECT ia_decision FROM InfonaliaData LIMIT 1");
        } catch (\Exception $e) {
            $hasIa = false;
        }

        // Detectar columnas disponibles
        $srcColumns = collect(DB::connection($connection)->select("DESCRIBE InfonaliaData"))
            ->pluck('Field')->toArray();

        $baseCols = array_intersect($srcColumns, [
            'id', 'decision', 'fecha_publicacion', 'cliente', 'resumen_objeto', 'provincia',
            'presupuesto', 'presentacion', 'perfil_contratante', 'fecha_ingreso', 'url',
            'kanboard_task_id', 'updated_at', 'created_at',
            'ia_decision', 'ia_motivo', 'ia_fecha', 'revisado_humano', 'revisado_fecha',
        ]);
        $cols = implode(', ', $baseCols);
        $hasCreatedAt = in_array('created_at', $baseCols);

        $whereClause = $hasCreatedAt
            ? "WHERE created_at >= ? OR updated_at >= ?"
            : "WHERE fecha_ingreso >= ? OR updated_at >= ?";

        $srcRows = DB::connection($connection)->select(
            "SELECT {$cols} FROM InfonaliaData {$whereClause}",
            [$since, $since]
        );

        $this->info("  Found: " . count($srcRows) . " records since {$since}");

        $inserted = 0;
        $updated = 0;

        foreach ($srcRows as $src) {
            $decisionKey = $src->decision ? strtolower(trim($src->decision)) : null;

            $data = [
                'id_company' => $companyId,
                'id_decision' => $decisionKey ? ($statuses[$decisionKey] ?? $defaultDecisionId) : $defaultDecisionId,
                'fecha_publicacion' => $src->fecha_publicacion,
                'cliente' => $src->cliente,
                'resumen_objeto' => $src->resumen_objeto,
                'provincia' => $src->provincia,
                'presupuesto' => $src->presupuesto,
                'presentacion' => $src->presentacion,
                'perfil_contratante' => $src->perfil_contratante,
                'fecha_ingreso' => $src->fecha_ingreso,
                'url' => $src->url,
                'kanboard_task_id' => $src->kanboard_task_id,
            ];

            if ($hasIa) {
                $iaKey = $src->ia_decision ? strtolower(trim($src->ia_decision)) : null;
                $data['id_ia_decision'] = $iaKey ? ($statuses[$iaKey] ?? null) : null;
                $data['ia_motivo'] = $src->ia_motivo;
                $data['ia_fecha'] = $src->ia_fecha;
                $data['revisado_humano'] = (bool) ($src->revisado_humano ?? false);
                $data['revisado_fecha'] = $src->revisado_fecha ?? null;
            }

            // Cliente via aliases
            if (!empty($src->cliente)) {
                $data['id_client'] = ClientAlias::resolveClientId($companyId, $src->cliente);
            }

            // Buscar si ya existe por cliente + objeto (evitar duplicados)
            $existing = InfonaliaData::where('id_company', $companyId)
                ->where('cliente', $src->cliente)
                ->where('resumen_objeto', $src->resumen_objeto)
                ->where('fecha_publicacion', $src->fecha_publicacion)
                ->first();

            if ($existing) {
                $existing->updateQuietly($data);
                $updated++;
            } else {
                $data['created_at'] = $src->created_at ?? $src->fecha_ingreso ?? now();
                $data['updated_at'] = $src->updated_at ?? now();
                InfonaliaData::create($data);
                $inserted++;
            }
        }

        $this->info("  Infonalia: {$inserted} inserted, {$updated} updated");
    }

    private function syncOfertas(int $companyId, string $connection, string $since): void
    {
        $this->info('Syncing Ofertas...');

        $statuses = OfferStatus::where('id_company', $companyId)
            ->pluck('id', 'status')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $types = OfferType::where('id_company', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $formulas = OfferFormula::where('id_company', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $defaultStatus = $statuses['pendiente'] ?? null;

        $srcOffers = DB::connection($connection)->select("
            SELECT o.*, d.linea_negocio, d.actividad_cliente, d.renovable, d.fidelizacion,
                   d.kanboard_task, d.provincia_oferta, d.responsable, d.workflow, d.url AS detail_url
            FROM cial_ofertas o
            LEFT JOIN cial_ofertas_has_details d ON d.id = o.id
            WHERE o.created_at >= ? OR o.updated_at >= ?
        ", [$since, $since]);

        $this->info("  Found: " . count($srcOffers) . " offers since {$since}");

        $inserted = 0;
        $updated = 0;
        $idMap = [];

        foreach ($srcOffers as $src) {
            $statusKey = $src->estado ? strtolower(str_replace('_', ' ', trim($src->estado))) : null;
            $typeKey = $src->tipo_licitacion ? strtolower(trim($src->tipo_licitacion)) : null;
            $clientId = !empty($src->cliente) ? ClientAlias::resolveClientId($companyId, $src->cliente) : null;

            $data = [
                'id_company' => $companyId,
                'codigo_proyecto' => $src->codigo_proyecto,
                'cliente' => $src->cliente,
                'id_client' => $clientId,
                'proyecto' => $src->proyecto,
                'objeto' => $src->objeto,
                'sector' => $src->sector,
                'id_offer_type' => $typeKey ? ($types[$typeKey] ?? null) : null,
                'id_offer_status' => $statusKey ? ($statuses[$statusKey] ?? $defaultStatus) : $defaultStatus,
                'temperatura' => !empty($src->temperatura ?? null) ? strtolower($src->temperatura) : null,
                'fecha_presentacion' => $src->fecha_presentacion,
                'importe_licitacion' => $src->importe_licitacion,
                'importe_estimado' => $src->importe_estimado,
                'duracion_meses' => $src->duracion_meses,
                'renovable' => in_array($src->renovable ?? '', ['Si', 'No', 'Desconocido']) ? $src->renovable : null,
                'fidelizacion' => in_array($src->fidelizacion ?? '', ['Nuevo', 'Cliente', 'Desconocido']) ? $src->fidelizacion : null,
                'kanboard_task' => $src->kanboard_task ?? null,
                'responsable' => $src->responsable ?? null,
                'provincia' => $src->provincia_oferta ?? null,
                'url' => $src->detail_url ?? null,
            ];

            // Buscar si ya existe por codigo_proyecto
            $existing = null;
            if ($src->codigo_proyecto) {
                $existing = Offer::where('id_company', $companyId)
                    ->where('codigo_proyecto', $src->codigo_proyecto)
                    ->first();
            }

            if ($existing) {
                $existing->update($data);
                $idMap[$src->id] = $existing->id;
                $updated++;
            } else {
                $data['created_at'] = $src->created_at;
                $data['updated_at'] = $src->updated_at;
                $offer = Offer::create($data);
                $idMap[$src->id] = $offer->id;
                $inserted++;
            }
        }

        $this->info("  Offers: {$inserted} inserted, {$updated} updated");

        // Sync dates for new/updated offers
        if (!empty($idMap)) {
            $srcIds = array_keys($idMap);
            $placeholders = implode(',', array_fill(0, count($srcIds), '?'));

            $srcDates = DB::connection($connection)->select(
                "SELECT * FROM cial_ofertas_has_dates WHERE oferta_id IN ({$placeholders})", $srcIds
            );
            $datesCount = 0;
            foreach ($srcDates as $src) {
                $newId = $idMap[$src->oferta_id] ?? null;
                if (!$newId) continue;
                $dateData = [];
                foreach (['fecha_anuncio', 'fecha_publicacion', 'fecha_adjudicacion', 'fecha_formalizacion', 'fecha_fin_contrato'] as $df) {
                    if (isset($src->$df)) $dateData[$df] = $src->$df;
                }
                if (!empty($dateData)) { Offer::where('id', $newId)->update($dateData); $datesCount++; }
            }

            $srcCrit = DB::connection($connection)->select(
                "SELECT * FROM cial_ofertas_has_criterios WHERE oferta_id IN ({$placeholders})", $srcIds
            );
            $critCount = 0;
            foreach ($srcCrit as $src) {
                $newId = $idMap[$src->oferta_id] ?? null;
                if (!$newId) continue;
                $fKey = $src->formula ? strtolower(trim($src->formula)) : null;
                Offer::where('id', $newId)->update([
                    'peso_economica' => $src->peso_economica, 'peso_tecnica' => $src->peso_tecnica,
                    'peso_objetiva_real' => $src->peso_objetiva_real, 'peso_objetiva_fake' => $src->peso_objetiva_fake,
                    'id_formula' => $fKey ? ($formulas[$fKey] ?? null) : null,
                ]);
                $critCount++;
            }

            $srcComp = DB::connection($connection)->select("
                SELECT hc.*, ex.razon_exclusion
                FROM cial_ofertas_has_competitors hc
                LEFT JOIN cial_ofertas_competitors_exclusion ex ON ex.competitor_id = hc.id
                WHERE hc.oferta_id IN ({$placeholders})
            ", $srcIds);
            $compCount = 0;
            $compMap = [];
            foreach ($srcComp as $src) {
                $newOfferId = $idMap[$src->oferta_id] ?? null;
                if (!$newOfferId) continue;
                $competitorId = CompetitorAlias::resolveCompetitorId($companyId, $src->competitor_nombre);
                $oc = OfferCompetitor::updateOrCreate(
                    ['id_offer' => $newOfferId, 'competitor_nombre' => $src->competitor_nombre],
                    ['id_competitor' => $competitorId, 'admision' => $src->admision ?? 'Pendiente', 'razon_exclusion' => $src->razon_exclusion ?? null]
                );
                $compMap[$src->id] = $oc->id;
                $compCount++;
            }

            $srcScores = DB::connection($connection)->select("
                SELECT * FROM cial_ofertas_competitors_has_cuantitativas
                WHERE competitor_id IN (" . implode(',', array_fill(0, max(count(array_keys($compMap)), 1), '?')) . ")
            ", array_keys($compMap) ?: [0]);
            $scoreCount = 0;
            foreach ($srcScores as $src) {
                $newOcId = $compMap[$src->competitor_id] ?? null;
                if (!$newOcId) continue;
                OfferCompetitorScore::updateOrCreate(
                    ['id_offer_competitor' => $newOcId],
                    ['tecnico' => $src->tecnico, 'economico' => $src->economico,
                     'objetivo_real' => $src->objetivo_real, 'objetivo_fake' => $src->objetivo_fake, 'precio' => $src->precio]
                );
                $scoreCount++;
            }

            $this->info("  Related: {$datesCount} dates, {$critCount} criteria, {$compCount} competitors, {$scoreCount} scores");
        }
    }
}
