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

class ImportAbsoluteOffers extends Command
{
    protected $signature = 'import:absolute-offers';
    protected $description = 'Import Absolute offers from absolute_import database';

    private int $companyId = 2; // Absolute

    public function handle(): void
    {
        $this->info('Loading lookups...');

        $statuses = OfferStatus::where('company_id', $this->companyId)
            ->pluck('id', 'status')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $types = OfferType::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $businessLines = OfferBusinessLine::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $activities = OfferClientActivity::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $workflows = OfferWorkflow::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $formulas = OfferFormula::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $n) => [strtolower(trim($n)) => $id])->toArray();

        $defaultStatus = $statuses['pendiente'] ?? null;

        // 1. Import offers + details
        $this->info('Importing offers...');
        $srcOffers = DB::connection('mysql')->select('
            SELECT o.*, d.linea_negocio, d.actividad_cliente, d.renovable, d.fidelizacion,
                   d.kanboard_task, d.provincia_oferta, d.responsable, d.infonalia_id, d.workflow, d.url AS detail_url
            FROM absolute_import.cial_ofertas o
            LEFT JOIN absolute_import.cial_ofertas_has_details d ON d.id = o.id
        ');

        $idMap = []; // old_id => new_id
        $bar = $this->output->createProgressBar(count($srcOffers));

        foreach ($srcOffers as $src) {
            $statusKey = $src->estado ? strtolower(str_replace('_', ' ', trim($src->estado))) : null;
            $typeKey = $src->tipo_licitacion ? strtolower(trim($src->tipo_licitacion)) : null;
            $blKey = $src->linea_negocio ? strtolower(trim($src->linea_negocio)) : null;
            $actKey = $src->actividad_cliente ? strtolower(trim($src->actividad_cliente)) : null;
            $wfKey = $src->workflow ? strtolower(trim($src->workflow)) : null;

            $clientId = null;
            if (!empty($src->cliente)) {
                $clientId = ClientAlias::resolveClientId($this->companyId, $src->cliente);
            }

            $offer = Offer::create([
                'company_id' => $this->companyId,
                'codigo_proyecto' => $src->codigo_proyecto,
                'cliente' => $src->cliente,
                'id_client' => $clientId,
                'proyecto' => $src->proyecto,
                'objeto' => $src->objeto,
                'sector' => $src->sector,
                'id_offer_type' => $typeKey ? ($types[$typeKey] ?? null) : null,
                'id_offer_status' => $statusKey ? ($statuses[$statusKey] ?? $defaultStatus) : $defaultStatus,
                'temperatura' => $src->temperatura ? strtolower($src->temperatura) : null,
                'fecha_presentacion' => $src->fecha_presentacion,
                'importe_licitacion' => $src->importe_licitacion,
                'importe_estimado' => $src->importe_estimado,
                'duracion_meses' => $src->duracion_meses,
                // Details
                'id_business_line' => $blKey ? ($businessLines[$blKey] ?? null) : null,
                'id_client_activity' => $actKey ? ($activities[$actKey] ?? null) : null,
                'id_workflow' => $wfKey ? ($workflows[$wfKey] ?? null) : null,
                'renovable' => in_array($src->renovable, ['Si', 'No', 'Desconocido']) ? $src->renovable : null,
                'fidelizacion' => in_array($src->fidelizacion, ['Nuevo', 'Cliente', 'Desconocido']) ? $src->fidelizacion : null,
                'kanboard_task' => $src->kanboard_task,
                'responsable' => $src->responsable,
                'provincia' => $src->provincia_oferta,
                'url' => $src->detail_url ?? $src->url ?? null,
                'created_at' => $src->created_at,
                'updated_at' => $src->updated_at,
            ]);

            $idMap[$src->id] = $offer->id;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(count($idMap) . ' offers imported.');

        // 2. Import dates
        $this->info('Importing dates...');
        $srcDates = DB::connection('mysql')->select('SELECT * FROM absolute_import.cial_ofertas_has_dates');
        $datesCount = 0;
        foreach ($srcDates as $src) {
            $newId = $idMap[$src->oferta_id] ?? null;
            if (!$newId) continue;

            Offer::where('id', $newId)->update([
                'fecha_anuncio' => $src->fecha_anuncio,
                'fecha_publicacion' => $src->fecha_publicacion,
                'fecha_adjudicacion' => $src->fecha_adjudicacion,
                'fecha_formalizacion' => $src->fecha_formalizacion,
                'fecha_fin_contrato' => $src->fecha_fin_contrato,
            ]);
            $datesCount++;
        }
        $this->info("$datesCount dates updated.");

        // 3. Import criterios
        $this->info('Importing criteria...');
        $srcCrit = DB::connection('mysql')->select('SELECT * FROM absolute_import.cial_ofertas_has_criterios');
        $critCount = 0;
        foreach ($srcCrit as $src) {
            $newId = $idMap[$src->oferta_id] ?? null;
            if (!$newId) continue;

            $fKey = $src->formula ? strtolower(trim($src->formula)) : null;

            Offer::where('id', $newId)->update([
                'peso_economica' => $src->peso_economica,
                'peso_tecnica' => $src->peso_tecnica,
                'peso_objetiva_real' => $src->peso_objetiva_real,
                'peso_objetiva_fake' => $src->peso_objetiva_fake,
                'id_formula' => $fKey ? ($formulas[$fKey] ?? null) : null,
            ]);
            $critCount++;
        }
        $this->info("$critCount criteria updated.");

        // 4. Import competitors
        $this->info('Importing competitors...');
        $srcComp = DB::connection('mysql')->select('
            SELECT hc.*, ex.razon_exclusion
            FROM absolute_import.cial_ofertas_has_competitors hc
            LEFT JOIN absolute_import.cial_ofertas_competitors_exclusion ex ON ex.competitor_id = hc.id
        ');
        $compMap = []; // old_hc_id => new_oc_id
        $compCount = 0;
        foreach ($srcComp as $src) {
            $newOfferId = $idMap[$src->oferta_id] ?? null;
            if (!$newOfferId) continue;

            $competitorId = CompetitorAlias::resolveCompetitorId($this->companyId, $src->competitor_nombre);

            $oc = OfferCompetitor::create([
                'id_offer' => $newOfferId,
                'competitor_nombre' => $src->competitor_nombre,
                'id_competitor' => $competitorId,
                'admision' => $src->admision ?? 'Pendiente',
                'razon_exclusion' => $src->razon_exclusion,
            ]);
            $compMap[$src->id] = $oc->id;
            $compCount++;
        }
        $this->info("$compCount competitors imported.");

        // 5. Import scores
        $this->info('Importing scores...');
        $srcScores = DB::connection('mysql')->select('SELECT * FROM absolute_import.cial_ofertas_competitors_has_cuantitativas');
        $scoreCount = 0;
        foreach ($srcScores as $src) {
            $newOcId = $compMap[$src->competitor_id] ?? null;
            if (!$newOcId) continue;

            OfferCompetitorScore::updateOrCreate(
                ['id_offer_competitor' => $newOcId],
                [
                    'tecnico' => $src->tecnico,
                    'economico' => $src->economico,
                    'objetivo_real' => $src->objetivo_real,
                    'objetivo_fake' => $src->objetivo_fake,
                    'precio' => $src->precio,
                ]
            );
            $scoreCount++;
        }
        $this->info("$scoreCount scores imported.");

        $this->newLine();
        $this->info('=== IMPORT COMPLETE ===');
        $this->table(
            ['Table', 'Count'],
            [
                ['Offers', count($idMap)],
                ['Dates', $datesCount],
                ['Criteria', $critCount],
                ['Competitors', $compCount],
                ['Scores', $scoreCount],
            ]
        );
    }
}
