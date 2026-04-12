<?php

namespace App\Filament\Resources\ValuationResource\Pages;

use App\Filament\Resources\ValuationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListValuations extends ListRecords
{
    protected static string $resource = ValuationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
