<?php

namespace App\Filament\Resources\InfonaliaDataResource\Pages;

use App\Filament\Resources\InfonaliaDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInfonaliaData extends EditRecord
{
    protected static string $resource = InfonaliaDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
