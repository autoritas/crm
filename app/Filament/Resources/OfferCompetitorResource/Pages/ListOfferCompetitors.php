<?php

namespace App\Filament\Resources\OfferCompetitorResource\Pages;

use App\Filament\Resources\OfferCompetitorResource;
use App\Filament\Traits\PersistsColumnToggles;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferCompetitors extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = OfferCompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')->color('success')
                ->url(OfferCompetitorResource::getUrl('import')),
            Actions\CreateAction::make(),
        ];
    }
}
