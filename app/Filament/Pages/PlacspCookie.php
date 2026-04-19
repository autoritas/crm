<?php

namespace App\Filament\Pages;

use App\Actions\SyncOfferDocumentsAction;
use App\Models\Offer;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Pagina admin para refrescar la cookie de sesion de PLACSP a mano.
 *
 * El provider PLACSPWebProvider lee la cookie desde el fichero indicado por
 * `services.placsp.cookie_file` (default storage/app/certs/placsp.cookie).
 * Aqui damos al admin una UI para pegarla desde su Chrome loggueado cuando
 * caduque (no se puede automatizar: PLACSP tiene anti-bot estricto).
 */
class PlacspCookie extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'Admin';
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Cookie PLACSP';
    protected static ?string $title = 'Cookie de sesión PLACSP';
    protected static ?string $slug = 'admin-placsp-cookie';
    protected static ?int $navigationSort = 30;
    protected static string $view = 'filament.pages.placsp-cookie';

    /**
     * No aparece en el menu lateral. Se accede desde la pantalla
     * Admin → Herramientas mediante una card.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'cookie' => $this->readCookieFile(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('cookie')
                    ->label('Cookie completa (valor del header Cookie: de Chrome)')
                    ->rows(8)
                    ->placeholder('JSESSIONID=...; LtpaToken2=...; ...')
                    ->helperText('Debe contener al menos JSESSIONID y LtpaToken2 del dominio contrataciondelestado.es'),
            ])
            ->statePath('data');
    }

    public function getCookiePath(): string
    {
        return config('services.placsp.cookie_file')
            ?: storage_path('app/certs/placsp.cookie');
    }

    private function readCookieFile(): string
    {
        $path = $this->getCookiePath();
        return is_file($path) ? trim((string) @file_get_contents($path)) : '';
    }

    public function save(): void
    {
        $data   = $this->form->getState();
        $cookie = trim($data['cookie'] ?? '');

        if ($cookie === '') {
            Notification::make()->title('La cookie está vacía')->danger()->send();
            return;
        }

        // Normaliza: Chrome a veces pega saltos de línea o espacios raros.
        $cookie = preg_replace('/\s+/', ' ', $cookie);

        if (! str_contains($cookie, 'JSESSIONID')) {
            Notification::make()
                ->title('¿Seguro que es la cookie completa?')
                ->body('No encuentro JSESSIONID en el valor que has pegado.')
                ->warning()
                ->send();
        }

        $path = $this->getCookiePath();
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        if (file_put_contents($path, $cookie . "\n") === false) {
            Notification::make()
                ->title('No se pudo escribir el fichero')
                ->body("Ruta: {$path}. Revisa permisos.")
                ->danger()->persistent()->send();
            return;
        }
        @chmod($path, 0640);

        Notification::make()
            ->title('Cookie guardada')
            ->body("{$path} · " . strlen($cookie) . ' bytes')
            ->success()->send();
    }

    /**
     * Prueba la cookie lanzando el sync sobre la oferta PLACSP más reciente
     * de la empresa activa. Útil para verificar al instante que la cookie
     * recién pegada funciona.
     */
    public function test(): void
    {
        $companyId = (int) session('current_company_id', 1);

        $offer = Offer::where('company_id', $companyId)
            ->where('url', 'like', '%contrataciondelestado%')
            ->whereNotNull('kanboard_task')
            ->orderByDesc('id')
            ->first();

        if (! $offer) {
            Notification::make()
                ->title('No hay oferta PLACSP con tarea Kanboard para probar')
                ->warning()->send();
            return;
        }

        try {
            $summary = app(SyncOfferDocumentsAction::class)->run($offer);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al probar la cookie')
                ->body($e->getMessage())
                ->danger()->persistent()->send();
            return;
        }

        $found    = $summary['found'] ?? 0;
        $attached = $summary['attached'] ?? 0;
        $skipped  = $summary['skipped_duplicate'] ?? 0;

        if ($found > 0) {
            Notification::make()
                ->title('✓ Cookie válida')
                ->body("Oferta #{$offer->id}: {$found} pliego(s) detectados, {$attached} adjuntados, {$skipped} ya estaban.")
                ->success()->persistent()->send();
        } else {
            $errs = ! empty($summary['errors']) ? implode("\n", $summary['errors']) : '0 documentos encontrados';
            Notification::make()
                ->title('⚠ Cookie posiblemente caducada')
                ->body("Oferta #{$offer->id} (URL {$offer->url}): {$errs}")
                ->warning()->persistent()->send();
        }
    }

    public function getViewData(): array
    {
        $path   = $this->getCookiePath();
        $exists = is_file($path);
        return [
            'cookie_path'   => $path,
            'cookie_exists' => $exists,
            'cookie_length' => $exists ? filesize($path) : 0,
            'cookie_mtime'  => $exists ? filemtime($path) : null,
        ];
    }
}
