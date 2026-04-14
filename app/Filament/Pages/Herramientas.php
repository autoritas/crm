<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Services\LegacySync\LegacySyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Herramientas extends Page
{
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Herramientas';

    protected static ?string $title = 'Admin - Herramientas';

    protected static ?string $slug = 'admin-herramientas';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.herramientas';

    public function getViewData(): array
    {
        return [
            'companies' => Company::orderBy('id')->get(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function syncAction(): Action
    {
        return Action::make('sync')
            ->label('Sincronizar')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Sincronizar datos legacy')
            ->modalDescription('Trae leads y ofertas desde la BD legacy correspondiente. Operación idempotente.')
            ->modalSubmitActionLabel('Sincronizar')
            ->action(function (array $arguments) {
                $companyId = (int) ($arguments['company'] ?? 0);
                $scope = $arguments['scope'] ?? 'all';

                if (! in_array($companyId, [1, 2], true)) {
                    Notification::make()->title('Empresa no válida')->danger()->send();
                    return;
                }

                $service = app(LegacySyncService::class);

                try {
                    $result = match ($scope) {
                        'leads' => ['leads' => $service->syncLeads($companyId)],
                        'offers' => ['offers' => $service->syncOffers($companyId)],
                        default => $service->syncAll($companyId),
                    };

                    $body = collect($result)->map(function ($v, $k) {
                        return ucfirst($k).': +'.($v['inserted'] ?? 0).' / ~'.($v['updated'] ?? 0);
                    })->implode(' | ');

                    Notification::make()
                        ->title('Sincronización completada')
                        ->body($body)
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Error en la sincronización')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
