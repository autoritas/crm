<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Las empresas se dan de alta en Stockflow Core, no aqui.
 * Esta pagina queda bloqueada: redirige al listado con un aviso.
 */
class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    public function mount(): void
    {
        Notification::make()
            ->title('Las empresas se crean en Core')
            ->body('Desde CRM solo se editan los ajustes locales.')
            ->warning()
            ->send();

        $this->redirect(CompanyResource::getUrl('index'));
    }
}
