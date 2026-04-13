<?php

namespace App\Filament\Resources\CompetitorCatalogResource\Pages;

use App\Filament\Resources\CompetitorCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompetitorCatalog extends EditRecord
{
    protected static string $resource = CompetitorCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
