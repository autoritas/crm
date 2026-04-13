<?php

namespace App\Filament\Resources\ScreeningReasonResource\Pages;

use App\Filament\Resources\ScreeningReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScreeningReasons extends ListRecords
{
    protected static string $resource = ScreeningReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
