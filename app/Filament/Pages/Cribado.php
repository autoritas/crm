<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\InfonaliaData;
use App\Models\InfonaliaStatus;
use App\Models\Offer;
use App\Models\OfferStatus;
use App\Models\OfferType;
use App\Models\ScreeningReason;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Cribado extends Page
{
    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Cribado';

    protected static ?string $title = 'Cribado IA';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.cribado';

    public function getGroups(): array
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = InfonaliaStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        $items = InfonaliaData::where('company_id', $companyId)
            ->where('id_decision', $pendienteId)
            ->whereNotNull('id_ia_decision')
            ->with(['iaDecision', 'decision'])
            ->orderBy('id_ia_decision')
            ->orderByDesc('presupuesto')
            ->get();

        $groups = [];
        foreach ($items as $item) {
            $groupKey = $item->id_ia_decision ?? 0;
            $groupName = $item->iaDecision?->status ?? 'Sin clasificar';
            $groupColor = $item->iaDecision?->color ?? '#6b7280';

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'name' => $groupName,
                    'color' => $groupColor,
                    'items' => [],
                ];
            }
            $groups[$groupKey]['items'][] = $item;
        }

        return $groups;
    }

    public array $selectedItems = [];

    public function confirmAll(): void
    {
        $companyId = (int) session('current_company_id', 1);
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

        $this->selectedItems = [];
        Notification::make()->title("Confirmados {$count} items")->success()->send();
    }

    public function confirmSelected(): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()->title('No hay items seleccionados')->warning()->send();
            return;
        }

        $count = 0;
        foreach ($this->selectedItems as $id) {
            $record = InfonaliaData::find($id);
            if (!$record || !$record->id_ia_decision) continue;

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

        $this->selectedItems = [];
        Notification::make()->title("Confirmados {$count} items seleccionados")->success()->send();
    }

    public function bulkDecision(int $newDecision): void
    {
        if (empty($this->selectedItems)) {
            Notification::make()->title('No hay items seleccionados')->warning()->send();
            return;
        }

        $newStatus = InfonaliaStatus::find($newDecision);
        $count = 0;

        foreach ($this->selectedItems as $id) {
            $record = InfonaliaData::find($id);
            if (!$record) continue;

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

        $this->selectedItems = [];
        Notification::make()->title("Cambiados {$count} items a: {$newStatus->status}")->success()->send();
    }

    public function confirm(int $id): void
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
            Notification::make()->title('Confirmado: ' . $iaStatus->status . ' → Oferta creada + Kanboard')->success()->send();
        } else {
            Notification::make()->title('Confirmado: ' . ($iaStatus?->status ?? ''))->success()->send();
        }
    }

    public function override(int $id, int $newDecision, ?int $reasonId, ?string $comment): void
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

        // Determinar tipo de transicion y actualizar contexto de la empresa
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
            Notification::make()->title("Cambiado a: {$newStatus->status} → Oferta creada + Kanboard")->success()->send();
        } else {
            Notification::make()->title("Cambiado a: {$newStatus->status}")->success()->send();
        }
    }

    /**
     * Determina si la transicion requiere feedback en el contexto.
     * - generates_offer (Ofertar/Focus) → 'positive'
     * - Descartar/No Focus → 'negative'
     * - Dudoso/Pendiente → null (no requiere feedback)
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

        // Dudoso no genera feedback de contexto
        if (in_array(mb_strtolower($targetStatus->status), ['dudoso'])) {
            return null;
        }

        // Todo lo demas (Descartar, No Focus, etc.) es negativo
        return 'negative';
    }

    /**
     * Añade feedback humano al campo context de core.companies.
     * Este contexto es usado por la IA en futuros procesos de cribado.
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

        // Si la seccion ya existe, insertar bajo ella; si no, crearla al final
        if ($current && str_contains($current, $sectionHeader)) {
            // Buscar la posicion justo despues del header para insertar la nueva linea
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

        // Crear tarea en Kanboard
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

    public function getStatusOptions(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return InfonaliaStatus::where('company_id', $companyId)->pluck('status', 'id')->toArray();
    }

    /**
     * Mapa de status_id → tipo de transicion para el frontend.
     * 'ofertar' = genera oferta (motivo positivo requerido)
     * 'descartar' = descarte (motivo negativo requerido)
     * 'dudoso' / 'pendiente' = sin motivo requerido
     */
    public function getStatusTypes(): array
    {
        $companyId = (int) session('current_company_id', 1);
        $statuses = InfonaliaStatus::where('company_id', $companyId)->get();
        $types = [];

        foreach ($statuses as $s) {
            if ($s->generates_offer) {
                $types[$s->id] = 'ofertar';
            } elseif ($s->is_default_filter) {
                $types[$s->id] = 'pendiente';
            } elseif (in_array(mb_strtolower($s->status), ['dudoso'])) {
                $types[$s->id] = 'dudoso';
            } else {
                $types[$s->id] = 'descartar';
            }
        }

        return $types;
    }

    public function getNegativeReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('company_id', $companyId)
            ->where('type', 'negative')
            ->pluck('reason', 'id')->toArray();
    }

    public function getPositiveReasons(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return ScreeningReason::where('company_id', $companyId)
            ->where('type', 'positive')
            ->pluck('reason', 'id')->toArray();
    }

}
