<?php

namespace App\Filament\Resources\OfferStatusResource\Pages;

use App\Filament\Resources\OfferStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferStatus extends EditRecord
{
    protected static string $resource = OfferStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
