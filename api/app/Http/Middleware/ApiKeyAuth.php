<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->bearerToken() ?? $request->header('X-API-Key');

        if (! is_string($key) || $key === '') {
            return response()->json(['error' => 'missing_api_key'], 401);
        }

        $user = User::query()
            ->where('api_key_hash', User::hashKey($key))
            ->first();

        if (! $user) {
            return response()->json(['error' => 'invalid_api_key'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
