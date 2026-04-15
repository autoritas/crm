<?php

namespace App\Filament\Resources\ClientAliasResource\Pages;


use App\Filament\Traits\PersistsColumnToggles;
use App\Filament\Resources\ClientAliasResource;
use Filament\Resources\Pages\ListRecords;

class ListClientAliases extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = ClientAliasResource::class;

    public function getTitle(): string
    {
        return 'Sinonimos de clientes';
    }
}
