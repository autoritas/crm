<?php

namespace App\Filament\Resources\OfferCompetitorResource\Pages;

use App\Filament\Resources\OfferCompetitorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferCompetitor extends EditRecord
{
    protected static string $resource = OfferCompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
