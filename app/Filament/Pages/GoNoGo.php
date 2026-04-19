<?php

namespace App\Filament\Pages;

use App\Integrations\TenderSources\DownloadedDocument;
use App\Models\Company;
use App\Models\Offer;
use App\Models\OfferDocument;
use App\Models\OfferStatus;
use App\Models\OfferWorkflow;
use App\Services\KanboardAttachmentService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoNoGo extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Ofertas';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Go / No Go';
    protected static ?string $title = 'Go / No Go';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.go-nogo';

    public function getProspectsOffers(): array
    {
        return $this->getProspectsOfferModels()
            ->map(fn (Offer $o) => $this->formatOffer($o))
            ->values()
            ->all();
    }

    /**
     * Agrupa las ofertas por la recomendacion IA para la UI.
     * Claves del array: 'GO', 'GO_TACTICO', 'NO_GO', 'PENDIENTE'.
     * Cada valor es un array de ofertas ya formateadas.
     */
    public function getGroupedProspectsOffers(): array
    {
        $buckets = [
            'GO'         => [],
            'GO_TACTICO' => [],
            'NO_GO'      => [],
            'PENDIENTE'  => [],
        ];

        foreach ($this->getProspectsOfferModels() as $offer) {
            $key = in_array($offer->ia_go_nogo, ['GO', 'GO_TACTICO', 'NO_GO'], true)
                ? $offer->ia_go_nogo
                : 'PENDIENTE';
            $buckets[$key][] = $this->formatOffer($offer);
        }

        return $buckets;
    }

    /**
     * Carga los Offer Eloquent que viven en PROSPECTS + PENDIENTE y cuya
     * tarea Kanboard sigue en la columna PROSPECTS. Reusado por el listado
     * y por las acciones masivas.
     */
    private function getProspectsOfferModels(): \Illuminate\Support\Collection
    {
        $companyId = (int) session('current_company_id', 1);

        $pendienteId = OfferStatus::where('company_id', $companyId)
            ->where('is_default_filter', true)->value('id');

        $prospectsColId = OfferWorkflow::where('company_id', $companyId)
            ->where('name', 'PROSPECTS')->value('kanboard_column_id');

        $offers = Offer::where('company_id', $companyId)
            ->where('id_offer_status', $pendienteId)
            ->where('go_nogo', 'PENDIENTE')
            ->whereNotNull('kanboard_task')
            ->orderByDesc('created_at')
            ->get();

        if ($offers->isEmpty() || ! $prospectsColId) {
            return collect();
        }

        $taskIds = $offers->pluck('kanboard_task')->filter()->map(fn ($v) => (int) $v)->all();
        if (empty($taskIds)) return collect();

        $placeholders = implode(',', $taskIds);
        $tasks = DB::connection('kanboard')
            ->select("SELECT id FROM tasks WHERE id IN ({$placeholders}) AND column_id = ?", [$prospectsColId]);
        $prospectsTaskIds = collect($tasks)->pluck('id')->map(fn ($v) => (int) $v)->all();

        return $offers
            ->filter(fn (Offer $o) => in_array((int) $o->kanboard_task, $prospectsTaskIds, true))
            ->values();
    }

    private function formatOffer(Offer $offer): array
    {
        // Cuenta ficheros reales en la tarea Kanboard — si hay 0, la IA no
        // puede analizar y conviene sugerir "Solicitar pliegos" al usuario.
        $filesCount = 0;
        if ($offer->kanboard_task) {
            $filesCount = (int) DB::connection('kanboard')
                ->table('task_has_files')
                ->where('task_id', (int) $offer->kanboard_task)
                ->count();
        }

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
            'kanboard_task' => $offer->kanboard_task,
            'files_count' => $filesCount,
            'supports_sync' => $this->providerSupports($offer->url),
        ];
    }

    /**
     * True si alguno de nuestros providers puede procesar esta URL
     * (PLACSP, PSCP, ...). Evita mostrar "Solicitar pliegos" cuando no
     * tenemos scraper para el portal (ahorra clicks fallidos).
     */
    private function providerSupports(?string $url): bool
    {
        if (! $url) return false;
        $detector = app(\App\Integrations\TenderSources\SourceDetector::class);
        return $detector->detect($url) !== null;
    }

    /**
     * Dispara la descarga de pliegos de la plataforma y los adjunta a la
     * tarea Kanboard. Reusa SyncOfferDocumentsAction (mismo flujo que el
     * scheduler + el hook de Cribado + el comando artisan).
     */
    public function syncDocuments(int $offerId): void
    {
        $offer = Offer::find($offerId);
        if (! $offer) {
            Notification::make()->title('Oferta no encontrada')->danger()->send();
            return;
        }

        try {
            $summary = app(\App\Actions\SyncOfferDocumentsAction::class)->run($offer);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al solicitar pliegos')
                ->body($e->getMessage())
                ->danger()->persistent()->send();
            return;
        }

        if (! empty($summary['errors']) && $summary['attached'] === 0) {
            Notification::make()
                ->title('No se pudieron adjuntar pliegos')
                ->body(implode("\n", $summary['errors']))
                ->danger()->persistent()->send();
            return;
        }

        $title = sprintf(
            'Pliegos: %d adjuntados · %d ya estaban · %d fallidos',
            $summary['attached'],
            $summary['skipped_duplicate'],
            $summary['failed']
        );
        Notification::make()
            ->title($title)
            ->body($summary['provider'] ? "Fuente: {$summary['provider']}" : null)
            ->success()->send();
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
     * Aplica masivamente la decision dada a todas las ofertas cuya recomendacion
     * IA coincida. Pensado para "estoy de acuerdo con toda la seccion".
     *
     * @param  string $iaDecision  'GO' | 'GO_TACTICO' | 'NO_GO'
     */
    public function bulkAcceptIaSection(string $iaDecision): void
    {
        if (! in_array($iaDecision, ['GO', 'GO_TACTICO', 'NO_GO'], true)) {
            Notification::make()->title('Seccion no valida')->danger()->send();
            return;
        }

        $offers = $this->getProspectsOfferModels()
            ->filter(fn (Offer $o) => $o->ia_go_nogo === $iaDecision);

        if ($offers->isEmpty()) {
            Notification::make()->title('No hay ofertas en esta seccion')->warning()->send();
            return;
        }

        $ok = 0;
        $fail = 0;
        $errors = [];

        if ($iaDecision === 'NO_GO') {
            // Para NO_GO: cambiar estado a "descartado" + cerrar tarea Kanboard.
            $companyId = $offers->first()->company_id;
            $discardStatusId = OfferStatus::where('company_id', $companyId)
                ->where('is_default_discard', true)->value('id');

            foreach ($offers as $offer) {
                try {
                    $offer->update([
                        'go_nogo'         => 'NO_GO',
                        'id_offer_status' => $discardStatusId ?? $offer->id_offer_status,
                    ]);
                    $this->closeKanboardTask($offer);
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    $errors[] = "Oferta #{$offer->id}: " . $e->getMessage();
                    Log::error('bulkAcceptIaSection NO_GO failed', [
                        'offer_id' => $offer->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // GO o GO_TACTICO: mover a OFERTAR en Kanboard.
            foreach ($offers as $offer) {
                try {
                    $offer->update(['go_nogo' => $iaDecision]);
                    $error = $this->moveKanboardTask($offer, 'OFERTAR');
                    if ($error) {
                        $fail++;
                        $errors[] = "Oferta #{$offer->id}: {$error}";
                    } else {
                        $ok++;
                    }
                } catch (\Throwable $e) {
                    $fail++;
                    $errors[] = "Oferta #{$offer->id}: " . $e->getMessage();
                    Log::error('bulkAcceptIaSection ' . $iaDecision . ' failed', [
                        'offer_id' => $offer->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $label = match ($iaDecision) {
            'GO'         => 'GO',
            'GO_TACTICO' => 'GO TACTICO',
            'NO_GO'      => 'NO GO',
        };

        if ($fail === 0) {
            Notification::make()
                ->title("Aplicado {$label} a {$ok} ofertas")
                ->success()->send();
        } else {
            Notification::make()
                ->title("Aplicado {$label}: {$ok} OK, {$fail} fallidas")
                ->body(implode("\n", array_slice($errors, 0, 5)))
                ->warning()->persistent()->send();
        }
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

        $company = Company::with(['settings', 'kanboardColumns'])->find($offer->company_id);
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

            $projectId = (int) ($task['project_id'] ?? $company->settings?->kanboard_project_id ?? 0);
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

    /**
     * Accion Filament para subir pliegos manualmente desde el disco del usuario.
     * Se adjuntan a la tarea Kanboard de la oferta (Kanboard se encarga del S3)
     * y se registran en `offer_documents` con provider='MANUAL' para dedup.
     *
     * Uso desde blade:
     *   wire:click="mountAction('uploadPliego', @js(['offer_id' => $offer['id']]))"
     */
    public function uploadPliegoAction(): Action
    {
        return Action::make('uploadPliego')
            ->label('Subir pliego')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading(function (array $arguments) {
                $offer = Offer::find($arguments['offer_id'] ?? 0);
                return 'Subir pliegos: ' . ($offer?->cliente ?? 'oferta');
            })
            ->modalDescription('Los ficheros se adjuntan a la tarea Kanboard de esta oferta. Se admiten varios a la vez (max. 50 MB cada uno).')
            ->form([
                Forms\Components\FileUpload::make('files')
                    ->label('Ficheros')
                    ->multiple()
                    ->required()
                    ->maxSize(50 * 1024) // KB
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/zip',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                    ])
                    ->storeFiles(false)
                    ->preserveFilenames(),
            ])
            ->modalSubmitActionLabel('Subir a Kanboard')
            ->action(function (array $data, array $arguments): void {
                $offerId = (int) ($arguments['offer_id'] ?? 0);
                $offer   = Offer::find($offerId);

                if (! $offer || ! $offer->kanboard_task) {
                    Notification::make()
                        ->title('La oferta no tiene tarea Kanboard asociada')
                        ->danger()->send();
                    return;
                }

                $projectId = (int) DB::connection('mysql')->table('company_settings')
                    ->where('company_id', $offer->company_id)
                    ->value('kanboard_project_id');

                if (! $projectId) {
                    Notification::make()
                        ->title('La empresa no tiene kanboard_project_id configurado')
                        ->danger()->send();
                    return;
                }

                $kanboard = app(KanboardAttachmentService::class);
                $uploaded = 0;
                $dedup    = 0;
                $failed   = 0;
                $errors   = [];

                foreach ($data['files'] as $file) {
                    /** @var \Illuminate\Http\UploadedFile $file */
                    $bytes    = @file_get_contents($file->getRealPath());
                    if ($bytes === false) {
                        $failed++;
                        $errors[] = "No pude leer {$file->getClientOriginalName()}";
                        continue;
                    }

                    $doc = new DownloadedDocument(
                        sourceUrl: 'manual://' . $file->getClientOriginalName(),
                        filename:  $file->getClientOriginalName(),
                        content:   $bytes,
                        mime:      $file->getMimeType(),
                    );

                    // Dedup: mismo sha256 ya adjuntado para esta oferta.
                    $existing = OfferDocument::where('offer_id', $offer->id)
                        ->where('sha256', $doc->sha256())
                        ->where('status', 'attached')
                        ->first();
                    if ($existing) {
                        $dedup++;
                        continue;
                    }

                    $fileId = $kanboard->attach($projectId, (int) $offer->kanboard_task, $doc);

                    if ($fileId === null) {
                        $failed++;
                        $errors[] = "Kanboard rechazo {$doc->filename}";
                        OfferDocument::updateOrCreate(
                            ['offer_id' => $offer->id, 'sha256' => $doc->sha256()],
                            [
                                'company_id'       => $offer->company_id,
                                'provider'         => 'MANUAL',
                                'source_url'       => $doc->sourceUrl,
                                'filename'         => $doc->filename,
                                'mime'             => $doc->mime,
                                'bytes'            => $doc->bytes(),
                                'kanboard_task_id' => (int) $offer->kanboard_task,
                                'kanboard_file_id' => null,
                                'status'           => 'failed',
                                'error'            => 'Kanboard createTaskFile devolvio null',
                            ],
                        );
                        continue;
                    }

                    OfferDocument::updateOrCreate(
                        ['offer_id' => $offer->id, 'sha256' => $doc->sha256()],
                        [
                            'company_id'       => $offer->company_id,
                            'provider'         => 'MANUAL',
                            'source_url'       => $doc->sourceUrl,
                            'filename'         => $doc->filename,
                            'mime'             => $doc->mime,
                            'bytes'            => $doc->bytes(),
                            'kanboard_task_id' => (int) $offer->kanboard_task,
                            'kanboard_file_id' => $fileId,
                            'status'           => 'attached',
                            'error'            => null,
                        ],
                    );
                    $uploaded++;
                }

                $title = "Pliegos: {$uploaded} subidos, {$dedup} ya estaban, {$failed} fallidos";
                if ($failed === 0) {
                    Notification::make()->title($title)->success()->send();
                } else {
                    Notification::make()
                        ->title($title)
                        ->body(implode("\n", array_slice($errors, 0, 5)))
                        ->warning()->persistent()->send();
                }
            });
    }
}
