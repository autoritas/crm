<?php

namespace App\Filament\Resources\CompanyResource\Pages;


use App\Filament\Traits\PersistsColumnToggles;
use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
