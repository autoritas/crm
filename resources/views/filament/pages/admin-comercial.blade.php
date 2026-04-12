<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

        {{-- Card: Empresas --}}
        <a href="{{ route('filament.admin.resources.companies.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/10">
                    <x-heroicon-o-building-office class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Empresas</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Logo, icono y configuracion</p>
                </div>
            </div>
        </a>

        {{-- Card: Infonalia Status --}}
        <a href="{{ route('filament.admin.resources.infonalia-statuses.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-tag class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estados Infonalia</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gestionar estados por empresa</p>
                </div>
            </div>
        </a>

    </div>
</x-filament-panels::page>
