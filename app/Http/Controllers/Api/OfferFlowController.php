<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyKanboardColumn;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferFlowController extends Controller
{
    /**
     * GET /api/flow/pending-leads
     * Items de Infonalia cuya decisión es un status que genera oferta,
     * pero que aún no tienen oferta creada.
     */
    public function pendingLeads(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id', 0);

        // Statuses que generan oferta
        $generatingIds = InfonaliaStatus::where('company_id', $companyId)
            ->where('generates_offer', true)
            ->pluck('id');

        if ($generatingIds->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $leads = InfonaliaData::where('company_id', $companyId)
            ->whereIn('id_decision', $generatingIds)
            ->whereDoesntHave('offer')
            ->select('id', 'company_id', 'id_client', 'cliente', 'resumen_objeto', 'provincia', 'presupuesto', 'presentacion', 'url', 'perfil_contratante')
            ->get();

        return response()->json(['data' => $leads, 'total' => $leads->count()]);
    }

    /**
     * POST /api/flow/create-offer-from-lead
     * Crea una oferta a partir de un lead de Infonalia.
     * Body: { infonalia_id }
     */
    public function createOfferFromLead(Request $request): JsonResponse
    {
        $request->validate(['infonalia_id' => 'required|integer|exists:infonalia_data,id']);

        $lead = InfonaliaData::findOrFail($request->infonalia_id);

        // Verificar que no existe ya una oferta para este lead
        $existing = Offer::where('id_infonalia_data', $lead->id)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe oferta para este lead',
                'offer_id' => $existing->id,
            ], 409);
        }

        // Status por defecto (Pendiente) para la nueva oferta
        $defaultStatus = OfferStatus::where('company_id', $lead->company_id)
            ->where('is_default_filter', true)
            ->first();

        // Tipo licitación por defecto: Concurso
        $defaultType = \App\Models\OfferType::where('company_id', $lead->company_id)
            ->where('name', 'Concurso')
            ->first();

        $offer = Offer::create([
            'company_id' => $lead->company_id,
            'id_infonalia_data' => $lead->id,
            'cliente' => $lead->cliente,
            'id_client' => $lead->id_client,
            'objeto' => $lead->resumen_objeto,
            'provincia' => $lead->provincia,
            'importe_licitacion' => $lead->presupuesto,
            'fecha_presentacion' => $lead->presentacion,
            'fecha_publicacion' => $lead->fecha_publicacion,
            'url' => $lead->url,
            'id_offer_status' => $defaultStatus?->id,
            'id_offer_type' => $defaultType?->id,
            'sector' => 'Público',
        ]);

        // Generar codigo_proyecto: YYYY + 000000ID
        $year = $lead->presentacion
            ? date('Y', strtotime($lead->presentacion))
            : date('Y');
        $codigoProyecto = $year . str_pad($offer->id, 6, '0', STR_PAD_LEFT);
        $offer->update(['codigo_proyecto' => $codigoProyecto]);

        // Actualizar kanboard_task_id en infonalia_data si no lo tenía
        if (!$lead->kanboard_task_id) {
            // Se actualizará después cuando n8n cree la tarea en Kanboard
        }

        // Config Kanboard para la respuesta
        $company = Company::with(['settings', 'kanboardColumns'])->find($lead->company_id);
        $prospectsColumn = $company?->kanboardColumns->firstWhere('name', 'PROSPECTS');

        return response()->json([
            'success' => true,
            'offer_id' => $offer->id,
            'codigo_proyecto' => $codigoProyecto,
            'company_id' => $offer->company_id,
            'cliente' => $offer->cliente,
            'objeto' => $offer->objeto,
            'url' => $lead->url,
            'fecha_presentacion' => $lead->presentacion?->format('Y-m-d') ?? null,
            'kanboard' => [
                'project_id' => $company?->settings?->kanboard_project_id,
                'prospects_column_id' => $prospectsColumn?->kanboard_column_id,
                'category_id' => $company?->settings?->kanboard_default_category_id,
                'owner_id' => $company?->settings?->kanboard_default_owner_id,
            ],
        ]);
    }

    /**
     * GET /api/flow/kanboard-config?company_id=X
     * Devuelve configuración de Kanboard para la empresa.
     */
    public function kanboardConfig(Request $request): JsonResponse
    {
        $companyId = $request->integer('company_id', 0);
        $company = Company::with(['settings', 'kanboardColumns'])->find($companyId);

        if (!$company || !$company->settings?->kanboard_project_id) {
            return response()->json(['error' => 'No kanboard config'], 404);
        }

        $prospectsColumn = $company->kanboardColumns->firstWhere('name', 'PROSPECTS');

        return response()->json([
            'project_id' => $company->settings->kanboard_project_id,
            'prospects_column_id' => $prospectsColumn?->kanboard_column_id,
            'columns' => $company->kanboardColumns->map(fn ($c) => [
                'kanboard_column_id' => $c->kanboard_column_id,
                'name' => $c->name,
                'position' => $c->position,
            ]),
        ]);
    }

    /**
     * POST /api/flow/discard-offer
     * Marca una oferta como descartada (cuando se cierra la tarea en Kanboard).
     * Body: { offer_id } o { kanboard_task_id, company_id }
     */
    public function discardOffer(Request $request): JsonResponse
    {
        $offer = null;

        if ($request->has('offer_id')) {
            $offer = Offer::find($request->offer_id);
        } elseif ($request->has('kanboard_task_id') && $request->has('company_id')) {
            $offer = Offer::where('company_id', $request->company_id)
                ->where('kanboard_task', $request->kanboard_task_id)
                ->first();
        }

        if (!$offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        $discardStatus = OfferStatus::where('company_id', $offer->company_id)
            ->where('is_default_discard', true)
            ->first();

        if (!$discardStatus) {
            return response()->json(['error' => 'No discard status configured'], 400);
        }

        $offer->update(['id_offer_status' => $discardStatus->id]);

        return response()->json([
            'success' => true,
            'offer_id' => $offer->id,
            'new_status' => $discardStatus->status,
        ]);
    }

    /**
     * POST /api/flow/update-offer-kanboard
     * Actualiza el kanboard_task_id de una oferta.
     * Body: { offer_id, kanboard_task_id }
     */
    public function updateOfferKanboard(Request $request): JsonResponse
    {
        $request->validate([
            'offer_id' => 'required|integer|exists:offers,id',
            'kanboard_task_id' => 'required',
        ]);

        $offer = Offer::findOrFail($request->offer_id);
        $offer->update(['kanboard_task' => $request->kanboard_task_id]);

        return response()->json(['success' => true, 'offer_id' => $offer->id]);
    }
}
