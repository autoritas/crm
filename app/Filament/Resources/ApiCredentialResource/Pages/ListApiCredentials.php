<?php

namespace App\Filament\Resources\ApiCredentialResource\Pages;


use App\Filament\Traits\PersistsColumnToggles;
use App\Filament\Resources\ApiCredentialResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiCredentials extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = ApiCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
