<?php

namespace App\Filament\Traits;

use App\Models\UserTablePreference;

/**
 * Persiste la selección de columnas visibles de una tabla Filament
 * por usuario entre sesiones (tabla `user_table_preferences`).
 *
 * Hooks usados:
 *  - updatedToggledTableColumns()  : Livewire lo llama al marcar/desmarcar
 *    cualquier columna (propiedad $toggledTableColumns de Filament 3).
 *  - bootedPersistsColumnToggles() : hook Livewire con sufijo de nombre
 *    de trait, se ejecuta tras hidratar el componente. Restauramos el
 *    estado persistido en BD si la sesión aún no lo tiene.
 */
trait PersistsColumnToggles
{
    /**
     * Devuelve la clave estable de la tabla para esta página.
     * Usa el slug del Resource si existe; si no, el FQCN de la clase.
     */
    protected function getPersistentTableKey(): string
    {
        $resource = static::$resource ?? null;

        if ($resource && method_exists($resource, 'getSlug')) {
            return $resource::getSlug();
        }

        return md5(static::class);
    }

    /**
     * Se dispara en cada click sobre el selector de columnas: guarda en BD
     * y también mantiene el session-store nativo de Filament para que la
     * misma request refleje el cambio sin refrescar.
     */
    public function updatedToggledTableColumns(): void
    {
        // Conservar comportamiento por defecto de Filament (persist in session).
        session()->put([
            $this->getTableColumnToggleFormStateSessionKey() => $this->toggledTableColumns,
        ]);

        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        UserTablePreference::saveToggledColumns(
            $userId,
            $this->getPersistentTableKey(),
            is_array($this->toggledTableColumns) ? $this->toggledTableColumns : [],
        );
    }

    /**
     * Hook Livewire con sufijo de trait: se llama automáticamente tras
     * la hidratación del componente en cada request. Si no hay estado
     * en la sesión (porque es una sesión nueva o expiró), lo cargamos
     * desde la BD y lo dejamos en la sesión para que el flujo normal
     * de Filament lo use.
     */
    public function bootedPersistsColumnToggles(): void
    {
        if (! method_exists($this, 'getTableColumnToggleFormStateSessionKey')) {
            return;
        }

        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $sessionKey = $this->getTableColumnToggleFormStateSessionKey();

        if (session()->has($sessionKey)) {
            return;
        }

        $saved = UserTablePreference::getToggledColumns(
            $userId,
            $this->getPersistentTableKey(),
        );

        if (is_array($saved) && count($saved)) {
            session()->put($sessionKey, $saved);
            $this->toggledTableColumns = $saved;
        }
    }
}
