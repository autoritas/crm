@php
    $infonaliaStatuses = \App\Models\InfonaliaStatus::all();
    $offerStatuses = \App\Models\OfferStatus::all();
@endphp
<style>
    /* Colores tenues de fila: Infonalia */
    @foreach($infonaliaStatuses as $status)
    tr.infonalia-row-{{ $status->id }} {
        background-color: {{ $status->color }}15 !important;
    }
    tr.infonalia-row-{{ $status->id }}:hover {
        background-color: {{ $status->color }}25 !important;
    }
    @endforeach

    /* Colores tenues de fila: Ofertas */
    @foreach($offerStatuses as $status)
    tr.offer-row-{{ $status->id }} {
        background-color: {{ $status->color }}15 !important;
    }
    tr.offer-row-{{ $status->id }}:hover {
        background-color: {{ $status->color }}25 !important;
    }
    @endforeach

    /* =========================================
       TODO EN 1 FILA
       ========================================= */

    .fi-ta { position: relative; }

    /* --- Ocultar: labels de filtros, filtros activos --- */
    .fi-ta-filters-above-content-ctn label.fi-fo-field-wrp-label { display: none !important; }
    .fi-ta-filter-indicators { display: none !important; }

    /* --- Filtros: fila compacta inline --- */
    .fi-ta-filters-above-content-ctn {
        padding: 0.4rem 0.75rem !important;
        padding-right: 20rem !important;
        border-bottom: 1px solid rgb(229 231 235) !important;
    }
    .fi-ta-filters-above-content-ctn .fi-ta-filters {
        display: flex !important;
        gap: 0.4rem !important;
        align-items: center !important;
    }
    .fi-ta-filters-above-content-ctn .fi-ta-filters form {
        display: flex !important;
        gap: 0.4rem !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        width: auto !important;
    }
    .fi-ta-filters-above-content-ctn .fi-ta-filters form > div {
        display: flex !important;
        gap: 0.4rem !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
    }
    .fi-ta-filters-above-content-ctn .fi-fo-field-wrp {
        min-width: 0 !important;
        flex: 0 0 auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .fi-ta-filters-above-content-ctn .fi-fo-field-wrp > div {
        gap: 0 !important;
    }

    /* --- Selects de filtro compactos --- */
    .fi-ta-filters-above-content-ctn select {
        padding: 0.25rem 1.8rem 0.25rem 0.5rem !important;
        font-size: 0.75rem !important;
        height: 2rem !important;
        min-height: 0 !important;
        max-width: 150px !important;
        border-radius: 0.375rem !important;
    }

    /* --- Toolbar (busqueda + toggler): a la derecha, misma fila --- */
    .fi-ta-header-toolbar {
        position: absolute !important;
        top: 0 !important;
        right: 0 !important;
        z-index: 10 !important;
        padding: 0.4rem 0.75rem !important;
        border: none !important;
        background: transparent !important;
        gap: 0.3rem !important;
    }

    /* --- Busqueda compacta --- */
    .fi-ta-header-toolbar .fi-ta-search-field input {
        font-size: 0.75rem !important;
        padding: 0.25rem 0.5rem 0.25rem 2rem !important;
        height: 2rem !important;
    }
</style>
