<?php

namespace App\Services;

use App\Integrations\TenderSources\DownloadedDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adjunta ficheros a una tarea Kanboard via JSON-RPC (`createTaskFile`).
 *
 * Kanboard se encarga del almacenamiento (S3 en nuestro caso, configurado
 * server-side); nosotros solo le pasamos el blob en base64.
 *
 * Endpoint y credenciales reaprovechados de GoNoGo.php. Si en el futuro
 * pasan a `api_credentials` por empresa, se centralizan aqui.
 */
class KanboardAttachmentService
{
    private const ENDPOINT = 'https://kanboard.cosmos-intelligence.com/jsonrpc.php';
    private const USER     = 'jsonrpc';
    private const TOKEN    = '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c';

    private const HTTP_TIMEOUT = 60;

    /**
     * Crea un fichero adjunto en la tarea Kanboard.
     *
     * @return int|null  El `file_id` devuelto por Kanboard, o null si fallo.
     */
    public function attach(int $projectId, int $taskId, DownloadedDocument $doc): ?int
    {
        try {
            $resp = Http::withBasicAuth(self::USER, self::TOKEN)
                ->timeout(self::HTTP_TIMEOUT)
                ->post(self::ENDPOINT, [
                    'jsonrpc' => '2.0',
                    'method'  => 'createTaskFile',
                    'id'      => 1,
                    'params'  => [
                        'project_id' => $projectId,
                        'task_id'    => $taskId,
                        'filename'   => $doc->filename,
                        'blob'       => $doc->base64(),
                    ],
                ]);

            if (! $resp->ok()) {
                Log::error('Kanboard createTaskFile HTTP error', [
                    'task_id' => $taskId, 'http' => $resp->status(), 'body' => $resp->body(),
                ]);
                return null;
            }

            $result = $resp->json('result');

            // Kanboard devuelve int (file_id) en exito, false en fallo.
            if (is_int($result) && $result > 0) {
                return $result;
            }

            Log::error('Kanboard createTaskFile rejected', [
                'task_id' => $taskId,
                'filename' => $doc->filename,
                'result'  => $result,
                'error'   => $resp->json('error.message'),
            ]);
            return null;

        } catch (\Throwable $e) {
            Log::error('Kanboard createTaskFile exception', [
                'task_id' => $taskId, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
