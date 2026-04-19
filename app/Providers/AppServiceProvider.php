<?php

namespace App\Providers;

use App\Integrations\TenderSources\Providers\PLACSP\PLACSPProvider;
use App\Integrations\TenderSources\Providers\PLACSP\PLACSPWebProvider;
use App\Integrations\TenderSources\Providers\PSCP\PSCPCatalunyaProvider;
use App\Integrations\TenderSources\SourceDetector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Detector de plataformas de contratacion publica.
        // Anadir nuevos providers a la lista segun se vayan necesitando
        // (Vortal, Ariba, Adquira...). El orden importa: gana el primero que
        // responde supports().
        $this->app->singleton(SourceDetector::class, function () {
            return new SourceDetector([
                // Orden intencional: el web-provider tiene prioridad porque
                // solo responde `supports()` si hay PLACSP_USER/PASSWORD en
                // .env. Si no, cae al provider de mTLS con el cert .p12.
                new PLACSPWebProvider(),
                new PLACSPProvider(),
                new PSCPCatalunyaProvider(),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Offer::observe(\App\Observers\OfferObserver::class);
    }
}
