<?php

namespace App\Filament\Resources\ApiCredentialResource\Pages;

use App\Filament\Resources\ApiCredentialResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiCredential extends EditRecord
{
    protected static string $resource = ApiCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
