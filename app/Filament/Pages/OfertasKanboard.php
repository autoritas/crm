<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Models\Offer;
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
 * Herramienta admin: lista las ofertas sin `kanboard_task` y permite
 * crear la tarea en Kanboard (o crearla y cerrarla si corresponde a
 * ofertas ya finalizadas).
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

    /** Valor especial del select que significa "crear en GANADO + cerrar". */
    private const OPTION_CLOSED = '__closed__';

    public static function shouldRegisterNavigation(): bool
    {
        // Solo visible desde el card en Herramientas.
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
                Tables\Columns\TextColumn::make('go_nogo')
                    ->label('Go/NoGo')->badge(),
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([25, 50, 100])
            ->actions([
                Tables\Actions\Action::make('create')
                    ->label('Crear en Kanboard')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading(fn (Offer $record) => "Crear tarea para oferta #{$record->id}")
                    ->modalSubmitActionLabel('Crear')
                    ->form(fn (Offer $record) => [
                        Select::make('column_name')
                            ->label('Columna destino')
                            ->options($this->columnOptions($record->company_id))
                            ->default(self::OPTION_CLOSED)
                            ->required()
                            ->helperText('«Cerrada (GANADO)» crea la tarea y la cierra inmediatamente.'),
                    ])
                    ->action(function (Offer $record, array $data) {
                        $error = $this->createKanboardTask($record, (string) $data['column_name']);
                        if ($error) {
                            Notification::make()->title('No se pudo crear la tarea')->body($error)->danger()->send();
                        } else {
                            Notification::make()->title("Tarea Kanboard creada para oferta #{$record->id}")->success()->send();
                        }
                    }),
            ]);
    }

    /**
     * Opciones del select: columnas de la empresa + opcion especial «Cerrada».
     */
    private function columnOptions(int $companyId): array
    {
        $company = Company::with('kanboardColumns')->find($companyId);
        $options = [];
        foreach ($company?->kanboardColumns ?? [] as $col) {
            $options[(string) $col->kanboard_column_id] = $col->name;
        }
        $options[self::OPTION_CLOSED] = 'Cerrada (en GANADO + cerrar)';
        return $options;
    }

    /**
     * Crea la tarea en Kanboard, guarda `kanboard_task` en la oferta y
     * — si el usuario eligio «Cerrada» — cierra la tarea.
     * Devuelve null si OK, o mensaje de error.
     */
    private function createKanboardTask(Offer $offer, string $choice): ?string
    {
        $company = Company::with(['settings', 'kanboardColumns'])->find($offer->company_id);
        $projectId = $company?->settings?->kanboard_project_id;
        if (!$projectId) {
            return 'La empresa no tiene kanboard_project_id configurado.';
        }

        $closeAfter = false;
        if ($choice === self::OPTION_CLOSED) {
            $ganado = $company->kanboardColumns->firstWhere('name', 'GANADO');
            if (!$ganado) return "No existe la columna 'GANADO' configurada.";
            $columnId = (int) $ganado->kanboard_column_id;
            $closeAfter = true;
        } else {
            $columnId = (int) $choice;
            if (!$columnId) return 'Columna no válida.';
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
                    'column_id'   => $columnId,
                    'category_id' => $company->settings?->kanboard_default_category_id,
                    'owner_id'    => $company->settings?->kanboard_default_owner_id,
                    'description' => $offer->proyecto ?: ($offer->objeto ?: ''),
                    'date_due'    => $dueDate,
                ],
            ]);

            $taskId = (int) ($resp->json('result') ?? 0);
            if (!$taskId) {
                $err = $resp->json('error.message') ?? 'respuesta vacia';
                Log::error('OfertasKanboard createTask error', [
                    'offer_id' => $offer->id, 'error' => $err, 'body' => $resp->body(),
                ]);
                return 'Kanboard rechazó createTask: ' . $err;
            }

            // 2) Guardar task_id en la oferta
            $offer->update(['kanboard_task' => $taskId]);

            // 3) External link (URL)
            if ($offer->url) {
                Http::withBasicAuth(...$auth)->post($endpoint, [
                    'jsonrpc' => '2.0',
                    'method'  => 'createExternalTaskLink',
                    'id'      => 2,
                    'params'  => [$taskId, $offer->url, 'is_a_dependency'],
                ]);
            }

            // 4) Si es «Cerrada», cerrar task
            if ($closeAfter) {
                $close = Http::withBasicAuth(...$auth)->post($endpoint, [
                    'jsonrpc' => '2.0',
                    'method'  => 'closeTask',
                    'id'      => 3,
                    'params'  => ['task_id' => $taskId],
                ]);
                if ($close->json('result') !== true) {
                    Log::warning('OfertasKanboard closeTask error', [
                        'offer_id' => $offer->id, 'task_id' => $taskId,
                        'body' => $close->body(),
                    ]);
                    // No consideramos fallo critico: la tarea ya se creo.
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('OfertasKanboard excepcion: ' . $e->getMessage());
            return 'Excepción: ' . $e->getMessage();
        }
    }
}
