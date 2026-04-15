<?php

namespace App\Services\LegacySync;

use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferBusinessLine;
use App\Models\OfferClientActivity;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\OfferWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza los datos desde las dos BDs legacy (gestion = Autoritas, absolute = Absolute)
 * hacia el modelo nuevo del CRM. Idempotente via (company_id, legacy_id).
 */
class LegacySyncService
{
    /** Mapea company_id (1=Autoritas, 2=Absolute) a la conexión legacy correspondiente. */
    private const CONNECTIONS = [
        1 => 'source_gestion',
        2 => 'source_absolute',
    ];

    public function syncAll(int $companyId): array
    {
        return [
            'leads' => $this->syncLeads($companyId),
            'offers' => $this->syncOffers($companyId),
        ];
    }

    public function syncLeads(int $companyId): array
    {
        $conn = $this->connectionFor($companyId);
        $statuses = InfonaliaStatus::where('company_id', $companyId)
            ->pluck('id', 'status')->all();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection($conn)->table('InfonaliaData')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($companyId, $statuses, &$inserted, &$updated, &$skipped) {
                foreach ($rows as $row) {
                    $idDecision = $statuses[$row->decision] ?? null;
                    $idIaDecision = $row->ia_decision ? ($statuses[$row->ia_decision] ?? null) : null;

                    $data = [
                        'company_id' => $companyId,
                        'id_decision' => $idDecision,
                        'id_ia_decision' => $idIaDecision,
                        'ia_motivo' => $row->ia_motivo,
                        'ia_fecha' => $row->ia_fecha,
                        'revisado_humano' => (bool) $row->revisado_humano,
                        'revisado_fecha' => $row->revisado_fecha,
                        'fecha_publicacion' => $row->fecha_publicacion,
                        'cliente' => $row->cliente,
                        'resumen_objeto' => $row->resumen_objeto,
                        'provincia' => $row->provincia,
                        'presupuesto' => $row->presupuesto,
                        'presentacion' => $row->presentacion,
                        'perfil_contratante' => $row->perfil_contratante,
                        'fecha_ingreso' => $row->fecha_ingreso,
                        'url' => $row->url,
                        'kanboard_task_id' => $row->kanboard_task_id,
                    ];

                    $existing = InfonaliaData::where('company_id', $companyId)
                        ->where('legacy_id', $row->id)->first();

                    if ($existing) {
                        $existing->fill($data)->save();
                        $updated++;
                    } else {
                        $model = new InfonaliaData($data);
                        $model->legacy_id = $row->id;
                        $model->save();
                        $inserted++;
                    }
                }
            });

        return compact('inserted', 'updated', 'skipped');
    }

    public function syncOffers(int $companyId): array
    {
        $conn = $this->connectionFor($companyId);

        $offerStatuses = OfferStatus::where('company_id', $companyId)->pluck('id', 'status')->all();
        $offerTypes = OfferType::where('company_id', $companyId)->pluck('id', 'name')->all();
        $businessLines = OfferBusinessLine::where('company_id', $companyId)->pluck('id', 'name')->all();
        $clientActivities = OfferClientActivity::where('company_id', $companyId)->pluck('id', 'name')->all();
        $workflows = OfferWorkflow::where('company_id', $companyId)->pluck('id', 'name')->all();

        $infonaliaMap = InfonaliaData::where('company_id', $companyId)
            ->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();

        $inserted = 0;
        $updated = 0;

        // Cargar dates en memoria indexado por oferta_id (tablas pequeñas)
        $datesByOffer = [];
        foreach (DB::connection($conn)->table('cial_ofertas_has_dates')->get() as $d) {
            $datesByOffer[$d->oferta_id] = $d;
        }

        DB::connection($conn)->table('cial_ofertas')
            ->orderBy('id')
            ->chunk(200, function ($rows) use (
                $companyId, $conn, $offerStatuses, $offerTypes, $businessLines,
                $clientActivities, $workflows, $infonaliaMap, $datesByOffer,
                &$inserted, &$updated
            ) {
                $ids = collect($rows)->pluck('id')->all();
                $details = DB::connection($conn)->table('cial_ofertas_has_details')
                    ->whereIn('id', $ids)->get()->keyBy('id');

                foreach ($rows as $row) {
                    $d = $details->get($row->id);
                    $dt = $datesByOffer[$row->id] ?? null;

                    $idInfonalia = null;
                    if ($d && $d->infonalia_id) {
                        $idInfonalia = $infonaliaMap[$d->infonalia_id] ?? null;
                    }

                    $data = [
                        'company_id' => $companyId,
                        'codigo_proyecto' => $row->codigo_proyecto,
                        'cliente' => $row->cliente,
                        'proyecto' => $row->proyecto,
                        'objeto' => $row->objeto,
                        'sector' => $row->sector,
                        'id_offer_type' => $row->tipo_licitacion ? ($offerTypes[$row->tipo_licitacion] ?? null) : null,
                        'id_offer_status' => $row->estado ? ($offerStatuses[$row->estado] ?? null) : null,
                        'fecha_presentacion' => $row->fecha_presentacion,
                        'importe_licitacion' => $row->importe_licitacion,
                        'importe_estimado' => $row->importe_estimado,
                        'duracion_meses' => $row->duracion_meses,
                        // details
                        'id_business_line' => $d && $d->linea_negocio ? ($businessLines[$d->linea_negocio] ?? null) : null,
                        'id_client_activity' => $d && $d->actividad_cliente ? ($clientActivities[$d->actividad_cliente] ?? null) : null,
                        'id_workflow' => $d && $d->workflow ? ($workflows[$d->workflow] ?? null) : null,
                        'renovable' => $d->renovable ?? null,
                        'fidelizacion' => $d->fidelizacion ?? null,
                        'kanboard_task' => $d->kanboard_task ?? null,
                        'responsable' => $d->responsable ?? null,
                        'provincia' => $d->provincia_oferta ?? null,
                        'url' => $d->url ?? null,
                        // dates
                        'fecha_publicacion' => $dt->fecha_publicacion ?? null,
                        'fecha_adjudicacion' => $dt->fecha_adjudicacion ?? null,
                        'fecha_formalizacion' => $dt->fecha_formalizacion ?? null,
                        'fecha_fin_contrato' => $dt->fecha_fin_contrato ?? null,
                    ];

                    $existing = Offer::where('company_id', $companyId)
                        ->where('legacy_id', $row->id)->first();

                    // id_infonalia_data: solo se fija si hemos podido resolverlo,
                    // nunca se sobrescribe a null en un registro existente que ya lo tenía.
                    if ($idInfonalia !== null) {
                        $data['id_infonalia_data'] = $idInfonalia;
                    }

                    if ($existing) {
                        $existing->fill($data)->save();
                        $updated++;
                    } else {
                        $model = new Offer($data);
                        $model->legacy_id = $row->id;
                        if ($idInfonalia !== null) {
                            $model->id_infonalia_data = $idInfonalia;
                        }
                        $model->save();
                        $inserted++;
                    }
                }
            });

        return compact('inserted', 'updated');
    }

    private function connectionFor(int $companyId): string
    {
        if (! isset(self::CONNECTIONS[$companyId])) {
            throw new \InvalidArgumentException("No hay conexión legacy configurada para la empresa $companyId.");
        }

        return self::CONNECTIONS[$companyId];
    }
}
