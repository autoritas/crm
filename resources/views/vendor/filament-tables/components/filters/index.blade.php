@props([
    'applyAction',
    'form',
])

<div {{ $attributes->class(['fi-ta-filters flex items-center gap-2']) }}>
    {{ $form }}

    <button
        type="button"
        wire:click="resetTableFiltersForm"
        wire:loading.attr="disabled"
        wire:target="resetTableFiltersForm"
        class="fi-ta-filters-reset-btn flex items-center justify-center rounded-lg text-gray-400 hover:text-danger-600 hover:bg-danger-50 h-8 w-8 shrink-0 transition"
        title="Limpiar filtros"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M5 6l1 14a2 2 0 002 2h8a2 2 0 002-2l1-14"/><path d="M10 11v6"/><path d="M14 11v6"/>
        </svg>
    </button>

    <x-filament::loading-indicator
        :attributes="
            \Filament\Support\prepare_inherited_attributes(
                new \Illuminate\View\ComponentAttributeBag([
                    'wire:loading.delay.' . config('filament.livewire_loading_delay', 'default') => '',
                    'wire:target' => 'tableFilters,applyTableFilters,resetTableFiltersForm',
                ])
            )->class(['h-4 w-4 text-gray-400'])
        "
    />

    @if ($applyAction->isVisible())
        {{ $applyAction }}
    @endif
</div>
