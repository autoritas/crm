<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY') ?? $request->query('api_key');

        if (!$apiKey || $apiKey !== config('app.api_key')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
