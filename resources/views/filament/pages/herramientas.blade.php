<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

        {{-- Card: Sincronizar datos legacy --}}
        <a href="{{ \App\Filament\Pages\SyncLegacy::getUrl() }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-arrow-path class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Sincronizar datos legacy</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Leads y ofertas desde BD legacy</p>
                </div>
            </div>
        </a>

        {{-- Card: Ofertas ↔ Kanboard --}}
        <a href="{{ \App\Filament\Pages\OfertasKanboard::getUrl() }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-squares-plus class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Ofertas ↔ Kanboard</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Crear tareas Kanboard para ofertas</p>
                </div>
            </div>
        </a>

        {{-- Card: Pendientes por fase --}}
        <a href="{{ \App\Filament\Pages\PendientesPorFase::getUrl() }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-clock class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Pendientes por fase</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Ofertas pendientes filtradas por fase Kanboard</p>
                </div>
            </div>
        </a>

        {{-- Card: Cookie PLACSP --}}
        <a href="{{ \App\Filament\Pages\PlacspCookie::getUrl() }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-key class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Cookie PLACSP</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Refrescar la sesión de PLACSP para bajada de pliegos</p>
                </div>
            </div>
        </a>

    </div>
</x-filament-panels::page>
