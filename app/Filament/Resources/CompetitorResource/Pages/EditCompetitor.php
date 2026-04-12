<?php

namespace App\Filament\Resources\CompetitorResource\Pages;

use App\Filament\Resources\CompetitorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompetitor extends EditRecord
{
    protected static string $resource = CompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
