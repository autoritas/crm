<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\ScreeningReason;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de Cribado IA.
 *
 * Centraliza toda la logica de negocio del cribado:
 *  - Confirmar decision de la IA
 *  - Cambiar decision (override) con motivo
 *  - Crear Oferta + tarea Kanboard (PROSPECTS)
 *  - Actualizar contexto de la empresa con feedback humano (positivo/negativo)
 *
 * Sustituye al workflow externo n8n que se habia montado para esta logica.
 */
class CribadoController extends Controller
{
    /**
     * Confirma la decision propuesta por la IA para un lead concreto.
     * Si la decision genera oferta, crea Offer + tarea Kanboard.
     *
     * @return array{status:string, message:string}
     */
    public function confirm(int $id): array
    {
        $record = InfonaliaData::findOrFail($id);
        $iaStatus = $record->iaDecision;

        $record->update([
            'id_decision' => $record->id_ia_decision,
            'revisado_humano' => true,
            'revisado_fecha' => now(),
        ]);

        if ($iaStatus && $iaStatus->generates_offer) {
            $this->createOfferAndKanboardTask($record);

            return [
                'status' => 'offer_created',
                'message' => 'Confirmado: ' . $iaStatus->status . ' → Oferta creada + Kanboard',
            ];
        }

        return [
            'status' => 'confirmed',
            'message' => 'Confirmado: ' . ($iaStatus?->status ?? ''),
        ];
    }

    /**
     * Cambia la decision de un lead a un nuevo estado.
     *
     * Reglas:
     *  - Si pasa a un estado que genera oferta (Ofertar/Focus): crea Offer + Kanboard
     *    y registra el motivo POSITIVO en core.companies.context.
     *  - Si pasa a un estado de descarte: registra el motivo NEGATIVO en context.
     *  - Si pasa a Dudoso/Pendiente: no requiere feedback de contexto.
     *
     * @return array{status:string, message:string}
     */
    public function override(int $id, int $newDecision, ?int $reasonId, ?string $comment): array
    {
        $record = InfonaliaData::findOrFail($id);
        $newStatus = InfonaliaStatus::find($newDecision);

        $record->update([
            'id_decision' => $newDecision,
            'revisado_humano' => true,
            'revisado_fecha' => now(),
            'id_screening_reason' => $reasonId,
            'screening_comment' => $comment,
        ]);

        $transitionType = $this->resolveTransitionType($newStatus);
        if ($transitionType && $reasonId) {
            $reason = ScreeningReason::find($reasonId);
            if ($reason) {
                $this->appendToCompanyContext(
                    $record->company_id,
                    $transitionType,
                    $reason->reason,
                    $comment,
                    $record->cliente
                );
            }
        }

        if ($newStatus && $newStatus->generates_offer) {
            $this->createOfferAndKanboardTask($record);

            return [
                'status' => 'offer_created',
                'message' => "Cambiado a: {$newStatus->status} → Oferta creada + Kanboard",
            ];
        }

        return [
            'status' => 'changed',
            'message' => "Cambiado a: " . ($newStatus?->status ?? ''),
        ];
    }

    /**
     * Confirma todos los leads pendientes con decision de IA para la company actual.
     *
     * @return array{count:int}
     */
    public function confirmAll(int $companyId): array
    {
        $pendienteId = InfonaliaStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)->value('id');

        $items = InfonaliaData::where('company_id', $companyId)
            ->where('id_decision', $pendienteId)
            ->whereNotNull('id_ia_decision')
            ->get();

        $count = 0;
        foreach ($items as $record) {
            $iaStatus = $record->iaDecision;
            $record->update([
                'id_decision' => $record->id_ia_decision,
                'revisado_humano' => true,
                'revisado_fecha' => now(),
            ]);
            if ($iaStatus && $iaStatus->generates_offer) {
                $this->createOfferAndKanboardTask($record);
            }
            $count++;
        }

        return ['count' => $count];
    }

    /**
     * Confirma un conjunto de leads seleccionados (acepta IDs explicitos).
     *
     * @param  int[]  $ids
     * @return array{count:int}
     */
    public function confirmSelected(array $ids): array
    {
        $count = 0;
        foreach ($ids as $id) {
            $record = InfonaliaData::find($id);
            if (!$record || !$record->id_ia_decision) {
                continue;
            }

            $iaStatus = $record->iaDecision;
            $record->update([
                'id_decision' => $record->id_ia_decision,
                'revisado_humano' => true,
                'revisado_fecha' => now(),
            ]);
            if ($iaStatus && $iaStatus->generates_offer) {
                $this->createOfferAndKanboardTask($record);
            }
            $count++;
        }

        return ['count' => $count];
    }

    /**
     * Aplica un cambio de decision masivo sobre los IDs indicados.
     *
     * @param  int[]  $ids
     * @return array{count:int, status:?string}
     */
    public function bulkDecision(array $ids, int $newDecision): array
    {
        $newStatus = InfonaliaStatus::find($newDecision);
        $count = 0;

        foreach ($ids as $id) {
            $record = InfonaliaData::find($id);
            if (!$record) {
                continue;
            }

            $record->update([
                'id_decision' => $newDecision,
                'revisado_humano' => true,
                'revisado_fecha' => now(),
            ]);
            if ($newStatus && $newStatus->generates_offer) {
                $this->createOfferAndKanboardTask($record);
            }
            $count++;
        }

        return ['count' => $count, 'status' => $newStatus?->status];
    }

    /**
     * Determina si la transicion requiere feedback en el contexto.
     *  - generates_offer (Ofertar/Focus) → 'positive'
     *  - Pendiente / Dudoso / Revisión * → null (no requiere motivo)
     *  - Resto (Descartar, No Focus) → 'negative'
     */
    private function resolveTransitionType(?InfonaliaStatus $targetStatus): ?string
    {
        if (!$targetStatus) {
            return null;
        }

        if ($targetStatus->generates_offer) {
            return 'positive';
        }

        if ($targetStatus->is_default_filter) {
            return null;
        }

        if (self::isReviewStatus($targetStatus->status)) {
            return null;
        }

        return 'negative';
    }

    /**
     * Determina si un nombre de estado equivale a "Dudoso / Revisión manual".
     * Cubre 'Dudoso' y cualquier variante 'Revisar ...' / 'Revisión ...'.
     */
    public static function isReviewStatus(?string $statusName): bool
    {
        if (!$statusName) {
            return false;
        }

        $normalized = mb_strtolower(trim($statusName));

        if ($normalized === 'dudoso') {
            return true;
        }

        return str_starts_with($normalized, 'revis');
    }

    /**
     * Anade feedback humano al campo context de core.companies.
     * Mantiene secciones separadas de motivos POSITIVOS y NEGATIVOS.
     * Este contexto es consumido por la IA en futuros procesos de cribado.
     */
    private function appendToCompanyContext(int $companyId, string $type, string $reason, ?string $comment, ?string $leadCliente): void
    {
        $current = DB::connection('autoritas_production')
            ->table('companies')
            ->where('id', $companyId)
            ->value('context');

        $sectionHeader = $type === 'positive'
            ? 'FEEDBACK HUMANO - MOTIVOS POSITIVOS (ofertar):'
            : 'FEEDBACK HUMANO - MOTIVOS NEGATIVOS (descartar):';

        $entry = "   - {$reason}";
        if ($comment) {
            $entry .= " — {$comment}";
        }
        if ($leadCliente) {
            $entry .= " (Lead: {$leadCliente})";
        }
        $entry .= ' [' . now()->format('Y-m-d') . ']';

        if ($current && str_contains($current, $sectionHeader)) {
            $pos = strpos($current, $sectionHeader);
            $afterHeader = $pos + strlen($sectionHeader);
            $newContext = substr($current, 0, $afterHeader) . "\n" . $entry . substr($current, $afterHeader);
        } else {
            $newContext = ($current ? $current . "\n\n" : '') . $sectionHeader . "\n" . $entry;
        }

        DB::connection('autoritas_production')
            ->table('companies')
            ->where('id', $companyId)
            ->update(['context' => $newContext]);

        Log::info("Cribado: contexto actualizado [{$type}]", [
            'company_id' => $companyId,
            'reason' => $reason,
            'lead' => $leadCliente,
        ]);
    }

    /**
     * Crea una Oferta a partir de un lead y la correspondiente tarea
     * en el tablero Kanboard de la empresa, en la columna PROSPECTS.
     *
     * Idempotente: si ya existe Offer para el lead, no hace nada.
     */
    private function createOfferAndKanboardTask(InfonaliaData $lead): void
    {
        if (Offer::where('id_infonalia_data', $lead->id)->exists()) {
            return;
        }

        $companyId = $lead->company_id;

        $defaultStatus = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)->first();
        $defaultType = OfferType::where('company_id', $companyId)
            ->where('name', 'Concurso')->first();

        $offer = Offer::create([
            'company_id' => $companyId,
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

        $year = $lead->presentacion ? Carbon::parse($lead->presentacion)->year : now()->year;
        $codigoProyecto = $year . str_pad($offer->id, 6, '0', STR_PAD_LEFT);
        $offer->update(['codigo_proyecto' => $codigoProyecto]);

        $company = Company::with(['settings', 'kanboardColumns'])->find($companyId);
        if (!$company || !$company->settings?->kanboard_project_id) {
            Log::warning('Cribado: empresa sin kanboard_project_id', ['company_id' => $companyId]);
            return;
        }

        $prospectsColumn = $company->kanboardColumns->firstWhere('name', 'PROSPECTS');
        if (!$prospectsColumn) {
            Log::warning('Cribado: empresa sin columna PROSPECTS', ['company_id' => $companyId]);
            return;
        }

        try {
            $dueDate = $lead->presentacion ? Carbon::parse($lead->presentacion)->format('Y-m-d') . ' 00:00' : null;

            $response = Http::withBasicAuth('jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c')
                ->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                    'jsonrpc' => '2.0',
                    'method' => 'createTask',
                    'id' => 1,
                    'params' => [
                        'title' => $lead->cliente ?? 'Sin título',
                        'project_id' => $company->settings->kanboard_project_id,
                        'column_id' => $prospectsColumn->kanboard_column_id,
                        'category_id' => $company->settings->kanboard_default_category_id,
                        'owner_id' => $company->settings->kanboard_default_owner_id,
                        'description' => ($lead->url ?? '') . "\n" . ($lead->resumen_objeto ?? ''),
                        'date_due' => $dueDate,
                    ],
                ]);

            $taskId = $response->json('result');

            if (!$taskId) {
                Log::error('Kanboard createTask rechazado', [
                    'offer_id' => $offer->id,
                    'company_id' => $companyId,
                    'error' => $response->json('error.message'),
                    'body' => $response->body(),
                ]);
                return;
            }

            $offer->update(['kanboard_task' => $taskId]);
            $lead->update(['kanboard_task_id' => $taskId]);

            if ($lead->url) {
                Http::withBasicAuth('jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c')
                    ->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                        'jsonrpc' => '2.0',
                        'method' => 'createExternalTaskLink',
                        'id' => 2,
                        'params' => [$taskId, $lead->url, 'is_a_dependency'],
                    ]);
            }
        } catch (\Throwable $e) {
            Log::error('Kanboard task creation failed: ' . $e->getMessage());
        }
    }
}
