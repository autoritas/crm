<x-filament-panels::page>
    <form wire:submit="import" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Base de datos a importar *
                </label>
                <select
                    wire:model="source_database"
                    class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    <option value="">— Selecciona la BD origen —</option>
                    <option value="gestion">gestion  (Autoritas · empresa 1)</option>
                    <option value="absolute">absolute (Absolute · empresa 2)</option>
                </select>
                @error('source_database') <span class="text-sm text-red-500">{{ $message }}</span> @enderror

                <p class="text-xs text-gray-400 mt-2">
                    Se sincronizarán oportunidades (<code>InfonaliaData</code>) y ofertas
                    (<code>cial_ofertas</code> + detalles + fechas) desde la BD indicada
                    hacia la empresa destino. El proceso es idempotente:
                    los registros ya importados se actualizan por <code>(id_company, legacy_id)</code>.
                </p>
            </div>
        </div>

        <div class="flex gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="import">
                <span wire:loading.remove wire:target="import">Importar</span>
                <span wire:loading wire:target="import">Importando...</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\OfferResource::getUrl('index') }}"
                color="gray"
            >
                Volver al listado
            </x-filament::button>
        </div>
    </form>

    @if($showResults)
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-lg font-semibold mb-4">Resultado de la importación</h3>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                    <span class="text-2xl font-bold text-green-600">{{ $inserted }}</span>
                    <p class="text-sm text-green-700 dark:text-green-400">Ofertas nuevas</p>
                </div>
                <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                    <span class="text-2xl font-bold text-blue-600">{{ $updated }}</span>
                    <p class="text-sm text-blue-700 dark:text-blue-400">Ofertas actualizadas</p>
                </div>
                <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-900/20">
                    <span class="text-2xl font-bold text-emerald-600">{{ $leadsInserted }}</span>
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">Leads nuevos</p>
                </div>
                <div class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-900/20">
                    <span class="text-2xl font-bold text-indigo-600">{{ $leadsUpdated }}</span>
                    <p class="text-sm text-indigo-700 dark:text-indigo-400">Leads actualizados</p>
                </div>
            </div>
        </div>
    @endif

    @if($errorMessage)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
            <h4 class="text-sm font-semibold text-red-600 mb-1">Error</h4>
            <p class="text-sm text-red-500">{{ $errorMessage }}</p>
        </div>
    @endif
</x-filament-panels::page>
