<?php

namespace App\Filament\Resources\OfferStatusResource\Pages;


use App\Filament\Traits\PersistsColumnToggles;
use App\Filament\Resources\OfferStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferStatuses extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = OfferStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
