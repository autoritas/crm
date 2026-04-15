<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\CompanyKanboardColumn;
use App\Models\Offer;
use App\Models\OfferStatus;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoNoGo extends Page
{
    protected static ?string $navigationGroup = 'Ofertas';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Go / No Go';
    protected static ?string $title = 'Go / No Go';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.go-nogo';

    public function getProspectsOffers(): array
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)->value('id');

        $prospectsColId = CompanyKanboardColumn::where('company_id', $companyId)
            ->where('name', 'PROSPECTS')->value('kanboard_column_id');

        $offers = Offer::where('company_id', $companyId)
            ->where('id_offer_status', $pendienteId)
            ->where('go_nogo', 'PENDIENTE')
            ->whereNotNull('kanboard_task')
            ->orderByDesc('created_at')
            ->get();

        // Filtrar solo las que están en PROSPECTS en Kanboard
        $taskIds = $offers->pluck('kanboard_task')->filter()->map(fn ($v) => (int) $v)->filter()->toArray();
        $prospectsTaskIds = [];

        if (!empty($taskIds) && $prospectsColId) {
            $placeholders = implode(',', $taskIds);
            $tasks = DB::connection('kanboard')
                ->select("SELECT id, column_id FROM tasks WHERE id IN ({$placeholders}) AND column_id = ?", [$prospectsColId]);
            $prospectsTaskIds = collect($tasks)->pluck('id')->toArray();
        }

        // Solo PROSPECTS - no incluir las sin kanboard_task
        $result = [];
        foreach ($offers as $offer) {
            if (!in_array((int) $offer->kanboard_task, $prospectsTaskIds)) continue;
            $result[] = $this->formatOffer($offer);
        }

        return $result;
    }

    private function formatOffer(Offer $offer): array
    {
        return [
            'id' => $offer->id,
            'cliente' => $offer->cliente,
            'objeto' => $offer->objeto,
            'presupuesto' => $offer->importe_licitacion,
            'fecha_presentacion' => $offer->fecha_presentacion,
            'url' => $offer->url,
            'codigo' => $offer->codigo_proyecto,
            'ia_go_nogo' => $offer->ia_go_nogo,
            'ia_analysis' => $offer->ia_go_nogo_analysis,
            'ia_date' => $offer->ia_go_nogo_date,
        ];
    }

    public function decideGo(int $offerId): void
    {
        $offer = Offer::findOrFail($offerId);
        $offer->update(['go_nogo' => 'GO']);

        $error = $this->moveKanboardTask($offer, 'OFERTAR');

        if ($error) {
            Notification::make()->title('GO guardado, pero Kanboard falló')->body($error)->danger()->send();
        } else {
            Notification::make()->title('GO — Movido a OFERTAR en Kanboard')->success()->send();
        }
    }

    public function decideGoTactico(int $offerId): void
    {
        $offer = Offer::findOrFail($offerId);
        $offer->update(['go_nogo' => 'GO_TACTICO']);

        $error = $this->moveKanboardTask($offer, 'OFERTAR');

        if ($error) {
            Notification::make()->title('GO TÁCTICO guardado, pero Kanboard falló')->body($error)->danger()->send();
        } else {
            Notification::make()->title('GO TÁCTICO — Movido a OFERTAR en Kanboard')->success()->send();
        }
    }

    public function decideNoGo(int $offerId): void
    {
        $offer = Offer::findOrFail($offerId);
        $companyId = $offer->company_id;

        $discardStatus = OfferStatus::where('company_id', $companyId)
            ->where('is_default_discard', true)->first();

        $offer->update([
            'go_nogo' => 'NO_GO',
            'id_offer_status' => $discardStatus?->id ?? $offer->id_offer_status,
        ]);

        // Cerrar tarea en Kanboard
        $this->closeKanboardTask($offer);

        Notification::make()->title('NO GO — Oferta descartada, tarea cerrada en Kanboard')->danger()->send();
    }

    /**
     * Mueve la tarea de Kanboard a la columna indicada.
     * Devuelve null si OK, o un mensaje de error si falla (para notificar).
     */
    private function moveKanboardTask(Offer $offer, string $columnName): ?string
    {
        if (!$offer->kanboard_task) {
            return 'La oferta no tiene tarea asociada en Kanboard.';
        }

        $company = Company::with('kanboardColumns')->find($offer->company_id);
        if (!$company) return 'Empresa no encontrada.';

        $column = $company->kanboardColumns->firstWhere('name', $columnName);
        if (!$column) return "No existe la columna '{$columnName}' configurada para la empresa.";

        $taskId = (int) $offer->kanboard_task;
        $endpoint = 'https://kanboard.cosmos-intelligence.com/jsonrpc.php';
        $auth = ['jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c'];

        try {
            // 1) Obtener la tarea para resolver project_id / swimlane_id reales.
            $taskResp = Http::withBasicAuth(...$auth)->post($endpoint, [
                'jsonrpc' => '2.0',
                'method' => 'getTask',
                'id' => 1,
                'params' => ['task_id' => $taskId],
            ]);

            $task = $taskResp->json('result');
            if (!$task) {
                $err = $taskResp->json('error.message') ?? 'Respuesta vacía';
                Log::error('Kanboard getTask failed', ['task_id' => $taskId, 'error' => $err, 'body' => $taskResp->body()]);
                return "Kanboard no devolvió la tarea {$taskId}: {$err}";
            }

            $projectId = (int) ($task['project_id'] ?? $company->kanboard_project_id ?? 0);
            $swimlaneId = (int) ($task['swimlane_id'] ?? 1);

            if (!$projectId) {
                return 'No se pudo determinar el project_id de Kanboard.';
            }

            // 2) Mover.
            $moveResp = Http::withBasicAuth(...$auth)->post($endpoint, [
                'jsonrpc' => '2.0',
                'method' => 'moveTaskPosition',
                'id' => 2,
                'params' => [
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'column_id' => (int) $column->kanboard_column_id,
                    'position' => 1,
                    'swimlane_id' => $swimlaneId ?: 1,
                ],
            ]);

            $result = $moveResp->json('result');
            if ($result !== true) {
                $err = $moveResp->json('error.message') ?? 'Kanboard devolvió false';
                Log::error('Kanboard moveTaskPosition failed', [
                    'task_id' => $taskId,
                    'project_id' => $projectId,
                    'column_id' => $column->kanboard_column_id,
                    'swimlane_id' => $swimlaneId,
                    'error' => $err,
                    'body' => $moveResp->body(),
                ]);
                return "Kanboard rechazó el movimiento: {$err}";
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Kanboard moveTask exception: ' . $e->getMessage());
            return 'Excepción al llamar a Kanboard: ' . $e->getMessage();
        }
    }

    private function closeKanboardTask(Offer $offer): void
    {
        if (!$offer->kanboard_task) return;

        try {
            Http::withBasicAuth('jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c')
                ->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                    'jsonrpc' => '2.0',
                    'method' => 'closeTask',
                    'id' => 1,
                    'params' => ['task_id' => (int) $offer->kanboard_task],
                ]);
        } catch (\Exception $e) {
            Log::error('Kanboard closeTask failed: ' . $e->getMessage());
        }
    }
}
