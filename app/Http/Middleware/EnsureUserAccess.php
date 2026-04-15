<?php

namespace App\Http\Middleware;

use App\Models\StockflowAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el usuario pierde el acceso al CRM (is_active=false, registro
 * eliminado en `stockflow_accesses`...), se cierra sesion.
 */
class EnsureUserAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user || !$user->is_active || !$user->hasAppAccess(StockflowAccess::myAppId())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => __('Tu acceso ha sido revocado.')]);
        }

        return $next($request);
    }
}
