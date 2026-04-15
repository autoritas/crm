<?php

namespace App\Traits;

use App\Models\Scopes\CompanyScope;

/**
 * Aplicable a cualquier modelo de negocio con columna `company_id`.
 *
 * - Aplica el {@see CompanyScope} como global scope (filtra por la
 *   compania del usuario autenticado; admin global ve todo).
 * - Asigna automaticamente `company_id` al crear el registro si el
 *   usuario tiene una compania.
 *
 * Nota: cada modelo sigue declarando su propia relacion `company()`
 * para no romper relaciones/labels ya existentes.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            if (empty($model->company_id) && auth()->check() && auth()->user()->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
