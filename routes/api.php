<?php

use App\Http\Controllers\Api\ClientNormalizationController;
use App\Http\Controllers\Api\OfferFlowController;
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
            'company_id' => $cred->id_company,
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

// Flujo Lead → Oferta → Kanboard
Route::middleware('api.key')->prefix('flow')->group(function () {
    Route::get('pending-leads', [OfferFlowController::class, 'pendingLeads']);
    Route::post('create-offer-from-lead', [OfferFlowController::class, 'createOfferFromLead']);
    Route::get('kanboard-config', [OfferFlowController::class, 'kanboardConfig']);
    Route::post('discard-offer', [OfferFlowController::class, 'discardOffer']);
    Route::post('update-offer-kanboard', [OfferFlowController::class, 'updateOfferKanboard']);
});

Route::middleware('api.key')->prefix('normalization')->group(function () {
    Route::get('unlinked-aliases', [ClientNormalizationController::class, 'unlinkedAliases']);
    Route::get('similar-clients', [ClientNormalizationController::class, 'similarClients']);
    Route::get('clients', [ClientNormalizationController::class, 'searchClients']);
    Route::post('link-alias', [ClientNormalizationController::class, 'linkAlias']);
    Route::post('create-and-link', [ClientNormalizationController::class, 'createAndLink']);
    Route::post('merge-clients', [ClientNormalizationController::class, 'mergeClients']);
});
