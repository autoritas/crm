<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferBusinessLine;
use App\Models\OfferClientActivity;
use App\Models\OfferWorkflow;
use App\Models\OfferFormula;
use App\Models\OfferCompetitor;
use App\Models\OfferCompetitorScore;
use App\Models\ClientAlias;
use App\Models\CompetitorAlias;

class ImportOffersFromDump extends Command
{
    protected $signature = 'import:offers-from-dump {company_id} {source_db}';
    protected $description = 'Import offers from a temporary source database into CRM';

    public function handle(): void
    {
        $companyId = (int) $this->argument('company_id');
        $sourceDb = $this->argument('source_db');

        $this->info("Importing for company {$companyId} from {$sourceDb}...");

        $statuses = OfferStatus::where('id_company', $companyId)
            ->pluck('id', 'status')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $types = OfferType::where('id_company', $companyId)
            ->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $businessLines = OfferBusinessLine::where('id_company', $companyId)
            ->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $activities = OfferClientActivity::where('id_company', $companyId)
            ->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $workflows = OfferWorkflow::where('id_company', $companyId)
            ->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();
        $formulas = OfferFormula::where('id_company', $companyId)
            ->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $defaultStatus = $statuses['pendiente'] ?? null;

        // 1. Offers + details
        $this->info('Importing offers...');
        $srcOffers = DB::select("
            SELECT o.*, d.linea_negocio, d.actividad_cliente, d.renovable, d.fidelizacion,
                   d.kanboard_task, d.provincia_oferta, d.responsable, d.infonalia_id, d.workflow, d.url AS detail_url
            FROM {$sourceDb}.cial_ofertas o
            LEFT JOIN {$sourceDb}.cial_ofertas_has_details d ON d.id = o.id
        ");

        $idMap = [];
        $bar = $this->output->createProgressBar(count($srcOffers));

        foreach ($srcOffers as $src) {
            $statusKey = $src->estado ? strtolower(str_replace('_', ' ', trim($src->estado))) : null;
            $typeKey = $src->tipo_licitacion ? strtolower(trim($src->tipo_licitacion)) : null;
            $blKey = $src->linea_negocio ? strtolower(trim($src->linea_negocio)) : null;
            $actKey = $src->actividad_cliente ? strtolower(trim($src->actividad_cliente)) : null;
            $wfKey = $src->workflow ? strtolower(trim($src->workflow)) : null;

            $clientId = !empty($src->cliente) ? ClientAlias::resolveClientId($companyId, $src->cliente) : null;

            $offer = Offer::create([
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
                'id_business_line' => $blKey ? ($businessLines[$blKey] ?? null) : null,
                'id_client_activity' => $actKey ? ($activities[$actKey] ?? null) : null,
                'id_workflow' => $wfKey ? ($workflows[$wfKey] ?? null) : null,
                'renovable' => in_array($src->renovable ?? '', ['Si', 'No', 'Desconocido']) ? $src->renovable : null,
                'fidelizacion' => in_array($src->fidelizacion ?? '', ['Nuevo', 'Cliente', 'Desconocido']) ? $src->fidelizacion : null,
                'kanboard_task' => $src->kanboard_task ?? null,
                'responsable' => $src->responsable ?? null,
                'provincia' => $src->provincia_oferta ?? null,
                'url' => $src->detail_url ?? null,
                'created_at' => $src->created_at,
                'updated_at' => $src->updated_at,
            ]);
            $idMap[$src->id] = $offer->id;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(count($idMap) . ' offers imported.');

        // 2. Dates
        $this->info('Importing dates...');
        $datesCount = 0;
        foreach (DB::select("SELECT * FROM {$sourceDb}.cial_ofertas_has_dates") as $src) {
            $newId = $idMap[$src->oferta_id] ?? null;
            if (!$newId) continue;
            $dateData = [];
            foreach (['fecha_anuncio', 'fecha_publicacion', 'fecha_adjudicacion', 'fecha_formalizacion', 'fecha_fin_contrato'] as $df) {
                if (isset($src->$df)) $dateData[$df] = $src->$df;
            }
            if (!empty($dateData)) Offer::where('id', $newId)->update($dateData);
            $datesCount++;
        }
        $this->info("$datesCount dates updated.");

        // 3. Criterios
        $this->info('Importing criteria...');
        $critCount = 0;
        foreach (DB::select("SELECT * FROM {$sourceDb}.cial_ofertas_has_criterios") as $src) {
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
        $this->info("$critCount criteria updated.");

        // 4. Competitors
        $this->info('Importing competitors...');
        $compMap = [];
        $compCount = 0;
        foreach (DB::select("
            SELECT hc.*, ex.razon_exclusion
            FROM {$sourceDb}.cial_ofertas_has_competitors hc
            LEFT JOIN {$sourceDb}.cial_ofertas_competitors_exclusion ex ON ex.competitor_id = hc.id
        ") as $src) {
            $newOfferId = $idMap[$src->oferta_id] ?? null;
            if (!$newOfferId) continue;
            $competitorId = CompetitorAlias::resolveCompetitorId($companyId, $src->competitor_nombre);
            $oc = OfferCompetitor::create([
                'id_offer' => $newOfferId, 'competitor_nombre' => $src->competitor_nombre,
                'id_competitor' => $competitorId,
                'admision' => $src->admision ?? 'Pendiente', 'razon_exclusion' => $src->razon_exclusion ?? null,
            ]);
            $compMap[$src->id] = $oc->id;
            $compCount++;
        }
        $this->info("$compCount competitors imported.");

        // 5. Scores
        $this->info('Importing scores...');
        $scoreCount = 0;
        foreach (DB::select("SELECT * FROM {$sourceDb}.cial_ofertas_competitors_has_cuantitativas") as $src) {
            $newOcId = $compMap[$src->competitor_id] ?? null;
            if (!$newOcId) continue;
            OfferCompetitorScore::updateOrCreate(
                ['id_offer_competitor' => $newOcId],
                ['tecnico' => $src->tecnico, 'economico' => $src->economico,
                 'objetivo_real' => $src->objetivo_real, 'objetivo_fake' => $src->objetivo_fake, 'precio' => $src->precio]
            );
            $scoreCount++;
        }
        $this->info("$scoreCount scores imported.");

        // 6. Normalizar competidores nuevos
        $this->info('Normalizing competitors...');
        $normalized = 0;
        foreach (\App\Models\CompetitorAlias::where('id_company', $companyId)->whereNull('id_competitor')->get() as $alias) {
            $key = \App\Services\ClientNormalizer::normalizeKey($alias->raw_name);
            $cleanName = \App\Services\ClientNormalizer::cleanName($key);
            $competitor = \App\Models\Competitor::firstOrCreate(
                ['id_company' => $companyId, 'name' => $cleanName]
            );
            $alias->update(['id_competitor' => $competitor->id]);
            OfferCompetitor::where('competitor_nombre', $alias->raw_name)
                ->whereHas('offer', fn ($q) => $q->where('id_company', $companyId))
                ->whereNull('id_competitor')
                ->update(['id_competitor' => $competitor->id]);
            $normalized++;
        }
        $this->info("$normalized competitor aliases normalized.");

        $this->newLine();
        $this->info('=== IMPORT COMPLETE ===');
        $this->table(['Table', 'Count'], [
            ['Offers', count($idMap)], ['Dates', $datesCount], ['Criteria', $critCount],
            ['Competitors', $compCount], ['Scores', $scoreCount], ['Normalized', $normalized],
        ]);
    }
}
