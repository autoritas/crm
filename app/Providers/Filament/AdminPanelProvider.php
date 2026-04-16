<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Login delegado a Stockflow Core (routes/web.php -> AuthController).
            // NO activamos ->login() aqui para que Filament redirija al login
            // de Laravel (ruta con nombre 'login') en vez de su pagina propia.
            ->brandName('CRM')
            ->brandLogo(fn () => view('filament.components.brand-logo'))
            ->favicon(asset('icon.svg'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make('Comercial'),
                NavigationGroup::make('Ofertas'),
                NavigationGroup::make('Admin'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->renderHook(
                'panels::topbar.start',
                fn () => view('filament.components.company-switcher'),
            )
            ->renderHook(
                'panels::topbar.end',
                fn () => ! app()->environment('production')
                    ? '<div style="position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:9999;background:#ef4444;color:white;font-size:12px;font-weight:800;letter-spacing:0.15em;padding:5px 20px;border-radius:6px;text-transform:uppercase;pointer-events:none;box-shadow:0 2px 8px rgba(239,68,68,0.4);">DESARROLLO</div>'
                    : '',
            )
            ->renderHook(
                'panels::sidebar.nav.start',
                fn () => view('filament.components.sidebar-toggle'),
            )
            ->renderHook(
                'panels::head.end',
                fn () => view('filament.components.dynamic-styles')->render() . '<style>
                    .fi-sidebar.sidebar-collapsed { width: 4.5rem !important; min-width: 4.5rem !important; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-nav-groups { overflow: hidden; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-group-label { display: none; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-item-label { display: none; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-item-badge { display: none; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-group-button { justify-content: center; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-item a { justify-content: center; padding-left: 0; padding-right: 0; }
                    .fi-sidebar.sidebar-collapsed .fi-sidebar-item .fi-sidebar-item-icon { margin: 0; }
                </style>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        if (localStorage.getItem("sidebar-collapsed") === "true") {
                            document.querySelector(".fi-sidebar")?.classList.add("sidebar-collapsed");
                        }
                    });
                </script>',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
