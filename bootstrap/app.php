<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // EnsureUserAccess: si el usuario pierde acceso al CRM en core,
        // lo deslogueamos automaticamente en cualquier peticion web.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserAccess::class,
        ]);

        $middleware->alias([
            'api.key' => \App\Http\Middleware\ValidateApiKey::class,
            'role' => \App\Http\Middleware\RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
