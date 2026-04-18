<?php

namespace App\Actions;

use App\Integrations\TenderSources\SourceDetector;
use App\Models\Offer;
use App\Models\OfferDocument;
use App\Services\KanboardAttachmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquesta: detecta provider → descarga pliegos → adjunta a Kanboard →
 * registra en `offer_documents` con dedup por sha256.
 *
 * Es idempotente: re-ejecutarla sobre la misma oferta no re-sube ficheros
 * ya adjuntados (salvo cambios en el origen, en cuyo caso el sha256 sera
 * distinto y sí se adjunta como version nueva).
 */
class SyncOfferDocumentsAction
{
    public function __construct(
        private SourceDetector $detector,
        private KanboardAttachmentService $kanboard,
    ) {}

    /**
     * Procesa una oferta. Devuelve el resumen para la UI/log.
     *
     * @return array{
     *   provider: ?string,
     *   found: int,
     *   attached: int,
     *   skipped_duplicate: int,
     *   failed: int,
     *   errors: string[],
     * }
     */
    public function run(Offer $offer): array
    {
        $summary = [
            'provider'          => null,
            'found'             => 0,
            'attached'          => 0,
            'skipped_duplicate' => 0,
            'failed'            => 0,
            'errors'            => [],
        ];

        if (! $offer->url) {
            $summary['errors'][] = 'La oferta no tiene URL.';
            return $summary;
        }

        if (! $offer->kanboard_task) {
            $summary['errors'][] = 'La oferta no tiene tarea Kanboard asociada.';
            return $summary;
        }

        $provider = $this->detector->detect($offer->url);
        if (! $provider) {
            $host = parse_url($offer->url, PHP_URL_HOST) ?: '(desconocido)';
            $summary['errors'][] = "Plataforma no soportada: {$host}";
            return $summary;
        }

        $summary['provider'] = $provider->id();

        try {
            $docs = $provider->fetchDocuments($offer);
        } catch (\Throwable $e) {
            Log::error('SyncOfferDocuments fetch failed', [
                'offer_id' => $offer->id, 'provider' => $provider->id(), 'error' => $e->getMessage(),
            ]);
            $summary['errors'][] = "Fallo leyendo plataforma: {$e->getMessage()}";
            return $summary;
        }

        $summary['found'] = count($docs);
        if (empty($docs)) {
            return $summary;
        }

        $projectId = $this->resolveKanboardProjectId($offer);
        if (! $projectId) {
            $summary['errors'][] = 'La empresa no tiene kanboard_project_id configurado.';
            return $summary;
        }

        foreach ($docs as $doc) {
            $sha = $doc->sha256();

            // Dedup: misma (offer_id, sha256) ya subida → saltar.
            $existing = OfferDocument::where('offer_id', $offer->id)
                ->where('sha256', $sha)
                ->first();

            if ($existing && $existing->status === 'attached') {
                $summary['skipped_duplicate']++;
                continue;
            }

            $fileId = $this->kanboard->attach(
                projectId: (int) $projectId,
                taskId:    (int) $offer->kanboard_task,
                doc:       $doc,
            );

            if ($fileId === null) {
                $summary['failed']++;
                $summary['errors'][] = "Fallo adjuntando: {$doc->filename}";

                // Registro el fallo para auditoria.
                OfferDocument::updateOrCreate(
                    ['offer_id' => $offer->id, 'sha256' => $sha],
                    [
                        'company_id'       => $offer->company_id,
                        'provider'         => $provider->id(),
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
                ['offer_id' => $offer->id, 'sha256' => $sha],
                [
                    'company_id'       => $offer->company_id,
                    'provider'         => $provider->id(),
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
            $summary['attached']++;
        }

        return $summary;
    }

    private function resolveKanboardProjectId(Offer $offer): ?int
    {
        $projectId = DB::connection('mysql')->table('company_settings')
            ->where('company_id', $offer->company_id)
            ->value('kanboard_project_id');

        return $projectId ? (int) $projectId : null;
    }
}
