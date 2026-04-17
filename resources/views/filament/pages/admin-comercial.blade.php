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
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/10">
                    <x-heroicon-o-tag class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estados Infonalia</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gestionar estados por empresa</p>
                </div>
            </div>
        </a>

        {{-- Card: Offer Status --}}
        <a href="{{ route('filament.admin.resources.offer-statuses.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-500/10">
                    <x-heroicon-o-clipboard-document-check class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estados Ofertas</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Gestionar estados de oferta por empresa</p>
                </div>
            </div>
        </a>

        {{-- Card: Offer Workflows (fases / columnas Kanboard) --}}
        <a href="{{ route('filament.admin.resources.offer-workflows.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-cyan-50 dark:bg-cyan-500/10">
                    <x-heroicon-o-view-columns class="h-6 w-6 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Estados Workflow</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Fases de oferta y columnas Kanboard asociadas</p>
                </div>
            </div>
        </a>

        {{-- Card: Offer Types --}}
        <a href="{{ route('filament.admin.resources.offer-types.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-500/10">
                    <x-heroicon-o-list-bullet class="h-6 w-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tipos Licitacion</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Tipos de licitacion por empresa</p>
                </div>
            </div>
        </a>

        {{-- Card: Motivos Cribado --}}
        <a href="{{ route('filament.admin.resources.screening-reasons.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-500/10">
                    <x-heroicon-o-funnel class="h-6 w-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Motivos Cribado</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Motivos positivos y negativos para IA</p>
                </div>
            </div>
        </a>

        {{-- Card: Competidores --}}
        <a href="{{ route('filament.admin.resources.competitor-catalogs.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-50 dark:bg-red-500/10">
                    <x-heroicon-o-user-group class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Competidores</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Catalogo normalizado de competidores</p>
                </div>
            </div>
        </a>

        {{-- Card: Credenciales API --}}
        <a href="{{ route('filament.admin.resources.api-credentials.index') }}"
           class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md hover:border-primary-500 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10">
                    <x-heroicon-o-key class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Credenciales API</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">n8n, OpenAI, Kanboard y otros servicios</p>
                </div>
            </div>
        </a>

    </div>
</x-filament-panels::page>
