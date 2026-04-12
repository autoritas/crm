<x-filament-panels::page>
    <form wire:submit="import" class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Archivo CSV *</label>
                <input
                    type="file"
                    wire:model="csv_file"
                    accept=".csv"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/20 dark:file:text-primary-400"
                />
                @error('csv_file') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-400 mt-1">Sube el CSV exportado. Se detectaran las columnas automaticamente.</p>

                <div wire:loading wire:target="csv_file" class="mt-2 text-sm text-primary-600">
                    Subiendo archivo...
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Delimitador *</label>
                <select wire:model="delimiter" class="block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=",">Coma (,)</option>
                    <option value=";">Punto y coma (;)</option>
                    <option value="&#9;">Tabulador</option>
                </select>
            </div>
        </div>

        <div class="flex gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="import,csv_file">
                <span wire:loading.remove wire:target="import">Importar</span>
                <span wire:loading wire:target="import">Importando...</span>
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\InfonaliaDataResource::getUrl('index') }}"
                color="gray"
            >
                Volver al listado
            </x-filament::button>
        </div>
    </form>

    @if($showResults)
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-lg font-semibold mb-4">Resultado de la importacion</h3>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                    <span class="text-2xl font-bold text-green-600">{{ $imported }}</span>
                    <p class="text-sm text-green-700 dark:text-green-400">Registros importados</p>
                </div>
                <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                    <span class="text-2xl font-bold text-red-600">{{ $skipped }}</span>
                    <p class="text-sm text-red-700 dark:text-red-400">Registros omitidos</p>
                </div>
            </div>

            @if(count($importErrors) > 0)
                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-red-600 mb-2">Errores (primeros 20):</h4>
                    <ul class="text-sm text-red-500 list-disc pl-5 space-y-1">
                        @foreach(array_slice($importErrors, 0, 20) as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
