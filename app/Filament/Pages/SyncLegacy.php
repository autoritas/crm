<?php

namespace App\Filament\Pages;

use App\Models\Company;
use App\Services\LegacySync\LegacySyncService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SyncLegacy extends Page
{
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Sincronización Legacy';

    protected static ?string $title = 'Sincronizar datos legacy';

    protected static ?string $slug = 'admin-sync-legacy';

    protected static ?int $navigationSort = 23;

    protected static string $view = 'filament.pages.sync-legacy';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getViewData(): array
    {
        return [
            'companies' => Company::orderBy('id')->get(),
        ];
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
