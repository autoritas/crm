<?php

namespace App\Filament\Resources\ScreeningReasonResource\Pages;

use App\Filament\Resources\ScreeningReasonResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScreeningReason extends EditRecord
{
    protected static string $resource = ScreeningReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
