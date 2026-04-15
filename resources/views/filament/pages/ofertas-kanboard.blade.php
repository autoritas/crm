<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">
        Listado de ofertas que <strong>no tienen tarea asociada en Kanboard</strong>.
        Usa el botón «Crear en Kanboard» de cada fila: elige la <strong>fase</strong> en la que debe quedar la oferta.
        La tarea se crea en la columna correspondiente a esa fase y <strong>se cierra</strong> automáticamente
        (la fase «Cerrada» va a la columna GANADO).
    </div>

    {{ $this->table }}
</x-filament-panels::page>
