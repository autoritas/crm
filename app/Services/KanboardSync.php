<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sincronizacion bidireccional entre ofertas del CRM y tareas de Kanboard.
 *
 * Reglas:
 *  - Cada `offer_workflows` fila mantiene `kanboard_column_id` = id de
 *    columna en Kanboard del tablero de la empresa.
 *  - Mover una oferta de fase en el CRM dispara un moveTaskPosition en
 *    Kanboard (via JSON-RPC) para que la tarea asociada siga a la fase.
 *  - Periodicamente (cron) se reconcilian: si en Kanboard alguien movio
 *    la tarea a otra columna, aplicamos el cambio a `offers.id_workflow`.
 *
 * La llamada a Kanboard esta encapsulada aqui para no esparcir el endpoint
 * ni las credenciales por el codigo.
 */
class KanboardSync
{
    private const ENDPOINT = 'https://kanboard.cosmos-intelligence.com/jsonrpc.php';
    private const AUTH_USER = 'jsonrpc';
    private const AUTH_TOKEN = '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c';

    /**
     * Lanza una llamada JSON-RPC a Kanboard y devuelve `result` o null.
     */
    private function rpc(string $method, array $params, int $id = 1): mixed
    {
        try {
            $resp = Http::withBasicAuth(self::AUTH_USER, self::AUTH_TOKEN)
                ->timeout(10)
                ->post(self::ENDPOINT, [
                    'jsonrpc' => '2.0',
                    'method'  => $method,
                    'id'      => $id,
                    'params'  => $params,
                ]);

            if (!$resp->ok()) {
                Log::error("Kanboard RPC {$method} HTTP {$resp->status()}", ['body' => $resp->body()]);
                return null;
            }

            $err = $resp->json('error');
            if ($err) {
                Log::error("Kanboard RPC {$method} error", ['error' => $err, 'params' => $params]);
                return null;
            }

            return $resp->json('result');
        } catch (\Throwable $e) {
            Log::error("Kanboard RPC {$method} exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mueve la tarea Kanboard de `$offer` a la columna de su fase actual.
     * Devuelve null si OK, o un mensaje legible si fallo.
     */
    public function pushOfferToKanboard(Offer $offer): ?string
    {
        if (!$offer->kanboard_task) return 'La oferta no tiene tarea en Kanboard.';
        if (!$offer->id_workflow)  return 'La oferta no tiene fase asignada.';

        $workflow = OfferWorkflow::find($offer->id_workflow);
        if (!$workflow || !$workflow->kanboard_column_id) {
            return "La fase '{$workflow?->name}' no tiene kanboard_column_id configurado.";
        }

        $task = $this->rpc('getTask', ['task_id' => (int) $offer->kanboard_task]);
        if (!$task) return 'Kanboard no devolvio la tarea.';

        $projectId  = (int) ($task['project_id']  ?? 0);
        $swimlaneId = (int) ($task['swimlane_id'] ?? 1) ?: 1;
        $currentCol = (int) ($task['column_id']   ?? 0);

        if (!$projectId) return 'La tarea no tiene project_id.';
        if ($currentCol === (int) $workflow->kanboard_column_id) {
            return null; // ya esta donde toca
        }

        $ok = $this->rpc('moveTaskPosition', [
            'project_id'  => $projectId,
            'task_id'     => (int) $offer->kanboard_task,
            'column_id'   => (int) $workflow->kanboard_column_id,
            'position'    => 1,
            'swimlane_id' => $swimlaneId,
        ], 2);

        return $ok === true ? null : 'Kanboard rechazo el movimiento.';
    }

    /**
     * Cierra una tarea Kanboard. Idempotente: si ya esta cerrada no pasa nada.
     */
    public function closeTask(int $taskId): bool
    {
        return $this->rpc('closeTask', ['task_id' => $taskId]) === true;
    }

    /**
     * Reabre una tarea Kanboard. Idempotente.
     */
    public function openTask(int $taskId): bool
    {
        return $this->rpc('openTask', ['task_id' => $taskId]) === true;
    }

    /**
     * Reconcilia ofertas con tareas Kanboard:
     *  (1) actualiza `offers.id_workflow` segun la columna real de la tarea,
     *  (2) aplica el estado de cierre segun `offer_workflows.closed_offer_status_id`
     *      cuando la tarea esta cerrada (is_active = 0),
     *  (3) si la tarea esta abierta pero la oferta tiene un estado de cierre
     *      configurado en alguna fase de la misma empresa, la devuelve al
     *      estado "pendiente" (`is_default_filter`).
     *
     * Usa mass updates (`Offer::whereKey->update`) para no disparar el observer
     * y evitar bucles CRM <-> Kanboard.
     *
     * @return array{checked:int, updated_wf:int, updated_status:int, skipped:int}
     */
    public function pullOffersFromKanboard(?int $companyId = null): array
    {
        $query = Offer::query()
            ->whereNotNull('kanboard_task')
            ->where('kanboard_task', '!=', '');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $offers = $query->select('id', 'company_id', 'kanboard_task', 'id_workflow', 'id_offer_status')->get();

        if ($offers->isEmpty()) {
            return ['checked' => 0, 'updated_wf' => 0, 'updated_status' => 0, 'skipped' => 0];
        }

        // Mapa company_id => [ col_id => [wf_id, closed_status_id|null] ]
        $wfRows = OfferWorkflow::whereNotNull('kanboard_column_id')
            ->get(['id', 'company_id', 'kanboard_column_id', 'closed_offer_status_id']);

        $wfMap = [];
        $closedStatuses = [];   // company_id => [status_id, ...] (dominio de "cerrados")
        foreach ($wfRows as $w) {
            $wfMap[$w->company_id][(int) $w->kanboard_column_id] = [
                'wf_id'    => $w->id,
                'close_to' => $w->closed_offer_status_id,
            ];
            if ($w->closed_offer_status_id) {
                $closedStatuses[$w->company_id][$w->closed_offer_status_id] = true;
            }
        }

        // Default (pendiente) por empresa
        $defaultFilterByCompany = OfferStatus::where('is_default_filter', true)
            ->get(['id', 'company_id'])
            ->pluck('id', 'company_id')
            ->toArray();

        // Leer column_id + is_active de cada tarea en una sola query.
        $taskIds = $offers->pluck('kanboard_task')->map(fn ($v) => (int) $v)->filter()->unique()->values()->toArray();
        if (empty($taskIds)) {
            return ['checked' => 0, 'updated_wf' => 0, 'updated_status' => 0, 'skipped' => 0];
        }

        $rows = DB::connection('kanboard')
            ->table('tasks')
            ->whereIn('id', $taskIds)
            ->get(['id', 'column_id', 'is_active']);

        $taskInfo = [];
        foreach ($rows as $r) {
            $taskInfo[(int) $r->id] = [
                'column_id' => (int) $r->column_id,
                'is_active' => (int) $r->is_active,
            ];
        }

        $checked = 0;
        $updatedWf = 0;
        $updatedStatus = 0;
        $skipped = 0;

        foreach ($offers as $offer) {
            $checked++;
            $taskId = (int) $offer->kanboard_task;
            $info = $taskInfo[$taskId] ?? null;
            if (!$info) { $skipped++; continue; }

            $kbCol = $info['column_id'];
            $entry = $wfMap[$offer->company_id][$kbCol] ?? null;
            if (!$entry) { $skipped++; continue; }

            $changes = [];

            // (1) Workflow
            if ((int) $offer->id_workflow !== (int) $entry['wf_id']) {
                $changes['id_workflow'] = $entry['wf_id'];
            }

            // (2) Estado segun abierto/cerrado
            if ($info['is_active'] === 0) {
                // Tarea cerrada: aplicar closed_offer_status_id si esta configurado.
                if ($entry['close_to']
                    && (int) $offer->id_offer_status !== (int) $entry['close_to']) {
                    $changes['id_offer_status'] = $entry['close_to'];
                }
            } else {
                // Tarea abierta: si el status actual es uno de los "cerrados"
                // configurados para la empresa, revertir a pendiente.
                $closedSet = $closedStatuses[$offer->company_id] ?? [];
                $default   = $defaultFilterByCompany[$offer->company_id] ?? null;

                if ($default
                    && $offer->id_offer_status
                    && isset($closedSet[$offer->id_offer_status])
                    && (int) $offer->id_offer_status !== (int) $default) {
                    $changes['id_offer_status'] = $default;
                }
            }

            if (empty($changes)) continue;

            Offer::whereKey($offer->id)->update($changes);
            if (isset($changes['id_workflow']))     $updatedWf++;
            if (isset($changes['id_offer_status'])) $updatedStatus++;
        }

        return [
            'checked'        => $checked,
            'updated_wf'     => $updatedWf,
            'updated_status' => $updatedStatus,
            'skipped'        => $skipped,
        ];
    }

    /**
     * Lista las columnas del tablero de una empresa (id + nombre + posicion).
     * Se lee directamente de la BD de Kanboard via conexion `kanboard`.
     *
     * @return array<int, array{id:int, title:string, position:int}>
     */
    public function listProjectColumns(int $kanboardProjectId): array
    {
        $rows = DB::connection('kanboard')
            ->table('columns')
            ->where('project_id', $kanboardProjectId)
            ->orderBy('position')
            ->get(['id', 'title', 'position']);

        return $rows->map(fn ($r) => [
            'id'       => (int) $r->id,
            'title'    => (string) $r->title,
            'position' => (int) $r->position,
        ])->values()->all();
    }
}
