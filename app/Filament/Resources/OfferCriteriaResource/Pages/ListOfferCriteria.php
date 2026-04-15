<?php

namespace App\Filament\Resources\OfferCriteriaResource\Pages;


use App\Filament\Resources\OfferCriteriaResource;
use App\Filament\Traits\PersistsColumnToggles;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferCriteria extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = OfferCriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')->color('success')
                ->url(OfferCriteriaResource::getUrl('import')),
        ];
    }
}
