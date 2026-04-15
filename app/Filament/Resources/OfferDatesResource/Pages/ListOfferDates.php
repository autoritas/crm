<?php

namespace App\Filament\Resources\OfferDatesResource\Pages;


use App\Filament\Resources\OfferDatesResource;
use App\Filament\Traits\PersistsColumnToggles;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferDates extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = OfferDatesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')->color('success')
                ->url(OfferDatesResource::getUrl('import')),
        ];
    }
}
