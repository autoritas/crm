<?php

namespace App\Filament\Resources\CompetitorResource\Pages;

use App\Filament\Resources\CompetitorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompetitors extends ListRecords
{
    protected static string $resource = CompetitorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
