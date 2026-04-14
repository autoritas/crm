<x-filament-panels::page>
    <div class="space-y-6">

        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                    <x-heroicon-o-arrow-path class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Sincronizar datos legacy</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Trae leads (InfonaliaData) y ofertas (cial_ofertas + details + dates) desde la BD legacy correspondiente a cada empresa. Idempotente por <code>legacy_id</code>.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($companies as $company)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $company->name }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    id_company: {{ $company->id }} ·
                                    BD: {{ $company->id === 1 ? 'gestion' : ($company->id === 2 ? 'absolute' : '—') }}
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            {{ ($this->syncAction)(['company' => $company->id, 'scope' => 'leads'])->label('Leads') }}
                            {{ ($this->syncAction)(['company' => $company->id, 'scope' => 'offers'])->label('Ofertas') }}
                            {{ ($this->syncAction)(['company' => $company->id, 'scope' => 'all'])->label('Todo') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</x-filament-panels::page>
