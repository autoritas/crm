<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
        Listado de ofertas que <strong>no tienen tarea asociada en Kanboard</strong>.
        Usa el botón «Crear en Kanboard» de cada fila para generarla.
        Selecciona «Cerrada (GANADO)» para las ofertas ya finalizadas — la tarea se crea y se cierra al mismo tiempo.
    </div>

    {{ $this->table }}
</x-filament-panels::page>
