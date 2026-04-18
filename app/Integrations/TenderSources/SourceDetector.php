<?php

namespace App\Integrations\TenderSources;

use App\Integrations\TenderSources\Contracts\TenderSourceProvider;

/**
 * Resuelve una URL de licitacion al provider que sabe tratarla.
 * Se inyecta con la lista de providers activos (configurables), pregunta por
 * host y devuelve el primero que responde `supports()`.
 */
class SourceDetector
{
    /** @param  TenderSourceProvider[]  $providers */
    public function __construct(private array $providers) {}

    public function detect(string $tenderUrl): ?TenderSourceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($tenderUrl)) {
                return $provider;
            }
        }
        return null;
    }

    /** @return TenderSourceProvider[] */
    public function providers(): array
    {
        return $this->providers;
    }
}
