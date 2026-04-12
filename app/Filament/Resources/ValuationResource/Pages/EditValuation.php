<?php

namespace App\Filament\Resources\ValuationResource\Pages;

use App\Filament\Resources\ValuationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditValuation extends EditRecord
{
    protected static string $resource = ValuationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
