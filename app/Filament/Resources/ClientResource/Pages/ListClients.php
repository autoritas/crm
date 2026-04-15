<?php

namespace App\Filament\Resources\ClientResource\Pages;


use App\Filament\Traits\PersistsColumnToggles;
use App\Filament\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
