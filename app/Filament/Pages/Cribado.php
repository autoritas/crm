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
use Filament\Forms;
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

        // Obtener el status "Pendiente" para filtrar decision = Pendiente
        $pendienteId = InfonaliaStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)
            ->value('id');

        // Agrupar por ia_decision
        $statuses = InfonaliaStatus::where('company_id', $companyId)->get()->keyBy('id');

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

        // Si el status genera oferta → crear oferta + tarea Kanboard
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

        // Si el nuevo status genera oferta → crear oferta + tarea Kanboard
        if ($newStatus && $newStatus->generates_offer) {
            $this->createOfferAndKanboardTask($record);
            Notification::make()->title("Cambiado a: {$newStatus->status} → Oferta creada + Kanboard")->success()->send();
        } else {
            Notification::make()->title("Cambiado a: {$newStatus->status}")->success()->send();
        }
    }

    private function createOfferAndKanboardTask(InfonaliaData $lead): void
    {
        // Verificar que no exista ya oferta para este lead
        if (Offer::where('id_infonalia_data', $lead->id)->exists()) {
            return;
        }

        $companyId = $lead->company_id;

        // Status y tipo por defecto
        $defaultStatus = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)->first();
        $defaultType = OfferType::where('company_id', $companyId)
            ->where('name', 'Concurso')->first();

        // Crear oferta
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

        // Generar codigo_proyecto
        $year = $lead->presentacion ? Carbon::parse($lead->presentacion)->year : now()->year;
        $codigoProyecto = $year . str_pad($offer->id, 6, '0', STR_PAD_LEFT);
        $offer->update(['codigo_proyecto' => $codigoProyecto]);

        // Crear tarea en Kanboard
        $company = Company::with('kanboardColumns')->find($companyId);
        if (!$company || !$company->kanboard_project_id) return;

        $prospectsColumn = $company->kanboardColumns->firstWhere('name', 'PROSPECTS');
        if (!$prospectsColumn) return;

        try {
            $dueDate = $lead->presentacion ? Carbon::parse($lead->presentacion)->format('Y-m-d') . ' 00:00' : null;

            $response = Http::withBasicAuth('jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c')
                ->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                    'jsonrpc' => '2.0',
                    'method' => 'createTask',
                    'id' => 1,
                    'params' => [
                        'title' => $lead->cliente ?? 'Sin título',
                        'project_id' => $company->kanboard_project_id,
                        'column_id' => $prospectsColumn->kanboard_column_id,
                        'category_id' => $company->kanboard_default_category_id,
                        'owner_id' => $company->kanboard_default_owner_id,
                        'description' => ($lead->url ?? '') . "\n" . ($lead->resumen_objeto ?? ''),
                        'date_due' => $dueDate,
                    ],
                ]);

            $taskId = $response->json('result');

            if ($taskId) {
                // Guardar task_id en la oferta y en infonalia
                $offer->update(['kanboard_task' => $taskId]);
                $lead->update(['kanboard_task_id' => $taskId]);

                // Añadir enlace externo
                if ($lead->url) {
                    Http::withBasicAuth('jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c')
                        ->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                            'jsonrpc' => '2.0',
                            'method' => 'createExternalTaskLink',
                            'id' => 2,
                            'params' => [$taskId, $lead->url, 'is_a_dependency'],
                        ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Kanboard task creation failed: ' . $e->getMessage());
        }
    }

    public function getStatusOptions(): array
    {
        $companyId = (int) session('current_company_id', 1);
        return InfonaliaStatus::where('company_id', $companyId)->pluck('status', 'id')->toArray();
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
