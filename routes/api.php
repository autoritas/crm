<?php

use App\Http\Controllers\Api\ClientNormalizationController;
use Illuminate\Support\Facades\Route;

// API protegida por token simple (header X-API-KEY)
Route::middleware('api.key')->group(function () {

    // Credenciales
    Route::get('credentials/{service}', function (\Illuminate\Http\Request $request, string $service) {
        $companyId = $request->integer('company_id', 0);
        $cred = \App\Models\ApiCredential::getForService($service, $companyId ?: null);
        if (!$cred) return response()->json(['error' => 'No credential found'], 404);
        return response()->json([
            'service' => $cred->service,
            'api_key' => $cred->api_key,
            'base_url' => $cred->base_url,
            'company_id' => $cred->company_id,
        ]);
    });

    // Sync desde origen
    Route::post('sync', function (\Illuminate\Http\Request $request) {
        $since = $request->input('since', now()->subDay()->toDateString());
        $company = $request->input('company', 'all');

        \Illuminate\Support\Facades\Artisan::call('sync:from-source', [
            '--since' => $since,
            '--company' => $company,
        ]);

        return response()->json([
            'success' => true,
            'output' => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    });

});

// Go/NoGo — solo ofertas en columna PROSPECTS de Kanboard
Route::middleware('api.key')->prefix('flow')->group(function () {
    Route::get('go-nogo-pending', function (\Illuminate\Http\Request $request) {
        $companyId = $request->integer('company_id', 0);

        // 1. Obtenemos el ID de la columna y el prompt
        $prospectsColId = \App\Models\OfferWorkflow::where('company_id', $companyId)
            ->where('name', 'PROSPECTS')
            ->value('kanboard_column_id');

        $prompt = \App\Models\CompanySetting::where('company_id', $companyId)
            ->value('go_nogo_model');

        if (!$prospectsColId) {
            return response()->json(['data' => [], 'prompt' => $prompt]);
        }

        // 2. Usamos tu SQL optimizado para obtener solo las ofertas que CUMPLEN todo
        // Nota: Usamos JOIN para asegurar que solo traiga ofertas con tareas activas y con archivos
        $offersData = \Illuminate\Support\Facades\DB::select("
            SELECT 
                o.id, 
                o.kanboard_task, 
                o.cliente, 
                o.objeto, 
                o.importe_licitacion, 
                o.url
            FROM offers o
            INNER JOIN kanboard.tasks kt ON kt.id = o.kanboard_task
            INNER JOIN kanboard.task_has_files kthf ON kt.id = kthf.task_id
            WHERE o.company_id = ?
            AND o.go_nogo = 'PENDIENTE'
            AND o.ia_go_nogo IS NULL
            AND o.id_workflow = 2
            AND kt.column_id = ?
            AND kt.is_active = 1
            GROUP BY o.id
            LIMIT 20
        ", [$companyId, $prospectsColId]);

        if (empty($offersData)) {
            return response()->json(['data' => [], 'prompt' => $prompt]);
        }

        // 3. Extraemos los IDs de las tareas para traer sus archivos de una sola vez
        $taskIds = collect($offersData)->pluck('kanboard_task')->toArray();

        $filesByTask = collect(
            \Illuminate\Support\Facades\DB::connection('kanboard')
                ->table('task_has_files')
                ->whereIn('task_id', $taskIds)
                ->get()
        )->groupBy('task_id');

        // 4. Formateamos el resultado final
        $result = array_map(function ($offer) use ($filesByTask) {
            $taskId = (int) $offer->kanboard_task;
            $files = $filesByTask->get($taskId) ?? collect();

            return [
                'offer_id' => $offer->id,
                'kanboard_task_id' => $taskId,
                'cliente' => $offer->cliente,
                'objeto' => $offer->objeto,
                'importe_licitacion' => $offer->importe_licitacion,
                'url' => $offer->url,
                'files' => $files->map(fn ($f) => [
                    'file_id' => (int) $f->id,
                    'name' => $f->name,
                    'path' => $f->path,
                    'size' => (int) $f->size,
                    'is_image' => (bool) $f->is_image,
                ])->values(),
                'prompt' => $prompt,
            ];
        }, $offersData);

        return response()->json(['data' => $result]);
    });

    Route::get('go-nogo-model', function (\Illuminate\Http\Request $request) {
        $companyId = $request->integer('company_id', 0);
        $model = \App\Models\CompanySetting::where('company_id', $companyId)->value('go_nogo_model');
        return response()->json(['model' => $model]);
    });

    Route::post('go-nogo-update', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'offer_id' => 'required|exists:offers,id',
            'ia_go_nogo' => 'required|in:GO,GO_TACTICO,NO_GO',
            'ia_analysis' => 'required|string',
        ]);
        $offer = \App\Models\Offer::findOrFail($request->offer_id);
        $offer->update([
            'ia_go_nogo' => $request->ia_go_nogo,
            'ia_go_nogo_analysis' => $request->ia_analysis,
            'ia_go_nogo_date' => now(),
        ]);

        // Añadir comentario en Kanboard
        if ($offer->kanboard_task) {
            $emoji = match($request->ia_go_nogo) {
                'GO' => '🟢',
                'GO_TACTICO' => '🟡',
                'NO_GO' => '🔴',
            };
            $comment = "{$emoji} ANÁLISIS IA - {$request->ia_go_nogo}\n\n{$request->ia_analysis}";

            try {
                \Illuminate\Support\Facades\Http::withBasicAuth(
                    'jsonrpc',
                    '9f80c6b25b7aa27c3ecca472ff61dade28a2c1c750f301e10bec4580596c'
                )->post('https://kanboard.cosmos-intelligence.com/jsonrpc.php', [
                    'jsonrpc' => '2.0',
                    'method' => 'createComment',
                    'id' => 1,
                    'params' => [
                        'task_id' => (int) $offer->kanboard_task,
                        'user_id' => 2,
                        'content' => $comment,
                    ],
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Kanboard comment failed: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'offer_id' => $offer->id]);
    });
});

Route::middleware('api.key')->prefix('normalization')->group(function () {
    Route::get('unlinked-aliases', [ClientNormalizationController::class, 'unlinkedAliases']);
    Route::get('similar-clients', [ClientNormalizationController::class, 'similarClients']);
    Route::get('clients', [ClientNormalizationController::class, 'searchClients']);
    Route::post('link-alias', [ClientNormalizationController::class, 'linkAlias']);
    Route::post('create-and-link', [ClientNormalizationController::class, 'createAndLink']);
    Route::post('merge-clients', [ClientNormalizationController::class, 'mergeClients']);
});
