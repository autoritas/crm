<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferWorkflow;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Herramienta admin: ofertas sin `kanboard_task`. Permite crear la
 * tarea Kanboard retrospectivamente eligiendo la FASE del workflow
 * en la que queremos colocarla. En todos los casos la tarea se crea
 * y se cierra inmediatamente (se trata de backfill historico).
 *
 * Mapping fase -> columna Kanboard:
 *  - Cada fase (`offer_workflows`) guarda su propio `kanboard_column_id`.
 *  - Si la fase se llama "Cerrada" o no tiene columna asignada, se usa
 *    como fallback la fase con nombre GANADO de la misma empresa.
 */
class OfertasKanboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';
    protected static ?string $navigationLabel = 'Ofertas ↔ Kanboard';
    protected static ?string $title = 'Ofertas sin tarea en Kanboard';
    protected static ?string $slug = 'admin-ofertas-kanboard';
    protected static ?int $navigationSort = 21;
    protected static string $view = 'filament.pages.ofertas-kanboard';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $companyId = (int) session('current_company_id', 1);

        return $table
            ->query(
                Offer::query()
                    ->where('company_id', $companyId)
                    ->whereNull('kanboard_task')
                    ->orderByDesc('fecha_presentacion')
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('codigo_proyecto')
                    ->label('Código')->searchable(),
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('proyecto')
                    ->label('Proyecto')->limit(40),
                Tables\Columns\TextColumn::make('fecha_presentacion')
                    ->label('Presentación')->date('d/m/Y')->sortable(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100])
            ->actions([
                Tables\Actions\Action::make('create')
                    ->label('Crear en Kanboard')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading(fn (Offer $record) => "Crear tarea para oferta #{$record->id}")
                    ->modalSubmitActionLabel('Crear y cerrar')
                    ->form(fn (Offer $record) => [
                        Select::make('workflow_id')
                            ->label('Fase')
                            ->options($this->workflowOptions($record->company_id))
                            ->default($this->defaultWorkflowId($record->company_id))
                            ->required()
                            ->helperText('La tarea se creara en la columna correspondiente a la fase y se cerrara. "Cerrada" va a la columna GANADO.'),
                    ])
                    ->action(function (Offer $record, array $data) {
                        $error = $this->createKanboardTask($record, (int) $data['workflow_id']);
                        if ($error) {
                            Notification::make()->title('No se pudo crear la tarea')->body($error)->danger()->send();
                            return;
                        }
                        Notification::make()->title("Oferta #{$record->id}: tarea creada y cerrada")->success()->send();
                        // Refresca la tabla para que la fila desaparezca (ya tiene kanboard_task).
                        $this->resetTable();
                    }),
            ]);
    }

    /**
     * Opciones del select: fases del workflow de la empresa.
     */
    private function workflowOptions(int $companyId): array
    {
        return OfferWorkflow::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Fase por defecto del select: "Cerrada" si existe, si no la ultima.
     */
    private function defaultWorkflowId(int $companyId): ?int
    {
        $cerrada = OfferWorkflow::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['cerrada'])
            ->value('id');

        if ($cerrada) return (int) $cerrada;

        return (int) OfferWorkflow::where('company_id', $companyId)
            ->orderByDesc('sort_order')
            ->value('id');
    }

    /**
     * Crea la tarea en Kanboard, guarda `kanboard_task` + `id_workflow` en
     * la oferta, y cierra la tarea. Devuelve null si OK, o mensaje de error.
     */
    private function createKanboardTask(Offer $offer, int $workflowId): ?string
    {
        $workflow = OfferWorkflow::where('company_id', $offer->company_id)
            ->where('id', $workflowId)->first();
        if (!$workflow) return 'Fase no válida.';

        $company = Company::with(['settings'])->find($offer->company_id);
        $projectId = $company?->settings?->kanboard_project_id;
        if (!$projectId) return 'La empresa no tiene kanboard_project_id configurado.';

        // Mapping fase -> columna Kanboard.
        // Si la fase es "Cerrada" o no tiene kanboard_column_id, caer a GANADO.
        $columnId = $workflow->kanboard_column_id;
        if (strcasecmp($workflow->name, 'Cerrada') === 0 || !$columnId) {
            $columnId = OfferWorkflow::where('company_id', $offer->company_id)
                ->where('name', 'GANADO')
                ->value('kanboard_column_id');
        }

        if (!$columnId) {
            return "No hay columna Kanboard para la fase '{$workflow->name}'.";
        }

        $endpoint = 'https://kanboard.cosmos-intelligence.com/jsonrpc.php';
        $auth = ['jsonrpc', '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c'];

        $dueDate = $offer->fecha_presentacion
            ? Carbon::parse($offer->fecha_presentacion)->format('Y-m-d') . ' 00:00'
            : null;

        try {
            // 1) createTask
            $resp = Http::withBasicAuth(...$auth)->post($endpoint, [
                'jsonrpc' => '2.0',
                'method'  => 'createTask',
                'id'      => 1,
                'params'  => [
                    'title'       => $offer->cliente ?: ('Oferta #' . $offer->id),
                    'project_id'  => (int) $projectId,
                    'column_id'   => (int) $columnId,
                    'category_id' => $company->settings?->kanboard_default_category_id,
                    'owner_id'    => $company->settings?->kanboard_default_owner_id,
                    'description' => $offer->proyecto ?: ($offer->objeto ?: ''),
                    'date_due'    => $dueDate,
                ],
            ]);

            $taskId = (int) ($resp->json('result') ?? 0);
            if (!$taskId) {
                $err = $resp->json('error.message') ?? 'respuesta vacía';
                Log::error('OfertasKanboard createTask error', [
                    'offer_id' => $offer->id, 'error' => $err, 'body' => $resp->body(),
                ]);
                return 'Kanboard rechazó createTask: ' . $err;
            }

            // 2) Guardar kanboard_task + id_workflow en la oferta
            $offer->update([
                'kanboard_task' => $taskId,
                'id_workflow'   => $workflow->id,
            ]);

            // 3) External link (URL) opcional
            if ($offer->url) {
                Http::withBasicAuth(...$auth)->post($endpoint, [
                    'jsonrpc' => '2.0',
                    'method'  => 'createExternalTaskLink',
                    'id'      => 2,
                    'params'  => [$taskId, $offer->url, 'is_a_dependency'],
                ]);
            }

            // 4) Cerrar la tarea (siempre, es backfill)
            $close = Http::withBasicAuth(...$auth)->post($endpoint, [
                'jsonrpc' => '2.0',
                'method'  => 'closeTask',
                'id'      => 3,
                'params'  => ['task_id' => $taskId],
            ]);
            if ($close->json('result') !== true) {
                Log::warning('OfertasKanboard closeTask', [
                    'offer_id' => $offer->id, 'task_id' => $taskId, 'body' => $close->body(),
                ]);
                // No es fallo crítico: la tarea ya se creó.
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('OfertasKanboard excepción: ' . $e->getMessage());
            return 'Excepción: ' . $e->getMessage();
        }
    }
}
