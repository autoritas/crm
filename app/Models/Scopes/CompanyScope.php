<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra los registros por `company_id` del usuario autenticado.
 *
 * Regla de tenancy del CRM (ver CLAUDE.md): ningun modelo de negocio
 * debe exponer datos de otra empresa. Los admins globales del CRM
 * (role_id=1) ven todo.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Admins del CRM ven todas las companias
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        if (!$user->company_id) {
            return;
        }

        $builder->where($model->getTable() . '.company_id', $user->company_id);
    }
}
