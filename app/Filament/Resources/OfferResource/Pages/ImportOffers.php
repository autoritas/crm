<?php

namespace App\Filament\Resources\OfferResource\Pages;

use App\Filament\Resources\OfferResource;
use App\Services\LegacySync\LegacySyncService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ImportOffers extends Page
{
    protected static string $resource = OfferResource::class;

    protected static string $view = 'filament.pages.import-offers';

    protected static ?string $title = 'Importar Ofertas';

    /** Mapea nombre de BD legacy -> company_id destino */
    private const DATABASE_MAP = [
        'gestion'  => 1, // Autoritas
        'absolute' => 2, // Absolute
    ];

    public string $source_database = '';

    public bool $showResults = false;
    public int $inserted = 0;
    public int $updated = 0;
    public int $leadsInserted = 0;
    public int $leadsUpdated = 0;
    public ?string $errorMessage = null;

    protected function getFormStatePath(): ?string
    {
        return null;
    }

    public function import(LegacySyncService $sync): void
    {
        $this->validate([
            'source_database' => 'required|string|in:gestion,absolute',
        ], [
            'source_database.in' => 'La base de datos debe ser "gestion" o "absolute".',
        ]);

        $db = strtolower(trim($this->source_database));
        $companyId = self::DATABASE_MAP[$db] ?? null;

        if (! $companyId) {
            Notification::make()
                ->title('Base de datos no reconocida')
                ->body('Solo se aceptan "gestion" (Autoritas) o "absolute" (Absolute).')
                ->danger()->send();
            return;
        }

        $this->showResults = false;
        $this->errorMessage = null;

        try {
            // Primero leads (oportunidades) para que las ofertas puedan enlazar id_infonalia_data
            $leads = $sync->syncLeads($companyId);
            $offers = $sync->syncOffers($companyId);

            $this->leadsInserted = $leads['inserted'] ?? 0;
            $this->leadsUpdated  = $leads['updated']  ?? 0;
            $this->inserted      = $offers['inserted'] ?? 0;
            $this->updated       = $offers['updated']  ?? 0;
            $this->showResults   = true;

            Notification::make()
                ->title("Importación completada desde '{$db}' (empresa {$companyId})")
                ->body("Ofertas: {$this->inserted} nuevas, {$this->updated} actualizadas. Leads: {$this->leadsInserted} nuevas, {$this->leadsUpdated} actualizadas.")
                ->success()->send();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            Notification::make()
                ->title('Error durante la importación')
                ->body($e->getMessage())
                ->danger()->send();
        }
    }
}
