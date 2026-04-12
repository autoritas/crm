<?php

namespace App\Filament\Resources\InfonaliaStatusResource\Pages;

use App\Filament\Resources\InfonaliaStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInfonaliaStatuses extends ListRecords
{
    protected static string $resource = InfonaliaStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
