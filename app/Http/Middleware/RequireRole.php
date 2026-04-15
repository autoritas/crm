<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Uso: ->middleware('role:1,2') para permitir admin o editor.
     */
    public function handle(Request $request, Closure $next, string ...$roleIds): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $required = array_map('intval', $roleIds);
        if (!$user->hasRole(...$required)) {
            abort(403, __('No tienes permisos para acceder a este recurso.'));
        }

        return $next($request);
    }
}
