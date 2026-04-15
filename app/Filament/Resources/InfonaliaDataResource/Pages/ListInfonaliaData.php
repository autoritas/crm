<?php

namespace App\Filament\Resources\InfonaliaDataResource\Pages;


use App\Filament\Resources\InfonaliaDataResource;
use App\Filament\Traits\PersistsColumnToggles;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInfonaliaData extends ListRecords
{
    use PersistsColumnToggles;

    protected static string $resource = InfonaliaDataResource::class;

    protected function getDefaultPaginationPageOption(): int
    {
        return 100;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Importar CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->url(InfonaliaDataResource::getUrl('import')),
            Actions\CreateAction::make(),
        ];
    }
}
