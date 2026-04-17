<?php

namespace App\Filament\Resources\OfferWorkflowResource\Pages;

use App\Filament\Resources\OfferWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOfferWorkflow extends EditRecord
{
    protected static string $resource = OfferWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
