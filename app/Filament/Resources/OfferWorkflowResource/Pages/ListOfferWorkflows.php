<?php

namespace App\Filament\Resources\OfferWorkflowResource\Pages;

use App\Filament\Resources\OfferWorkflowResource;
use App\Filament\Traits\PersistsColumnToggles;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfferWorkflows extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = OfferWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
