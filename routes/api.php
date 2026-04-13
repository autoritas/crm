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

    // Go/NoGo
    Route::get('go-nogo-pending', function (\Illuminate\Http\Request $request) {
        $companyId = $request->integer('company_id', 0);
        $offers = \App\Models\Offer::where('id_company', $companyId)
            ->where('go_nogo', 'PENDIENTE')
            ->whereNull('ia_go_nogo')
            ->whereNotNull('kanboard_task')
            ->select('id', 'kanboard_task', 'cliente', 'objeto', 'importe_licitacion', 'url')
            ->limit(10)
            ->get();
        return response()->json(['data' => $offers]);
    });

    Route::get('go-nogo-model', function (\Illuminate\Http\Request $request) {
        $companyId = $request->integer('company_id', 0);
        $model = \App\Models\Company::where('id', $companyId)->value('go_nogo_model');
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
