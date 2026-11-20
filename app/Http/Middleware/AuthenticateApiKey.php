<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-KEY');
        $configuredKey = (string) config('services.internal_api.key', '');

        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json(['message' => 'API key is required.'], 401);
        }

        if ($configuredKey === '' || ! hash_equals($configuredKey, $apiKey)) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $email = trim((string) config('services.internal_api.user_email', ''));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'Internal API user is unavailable.'], 503);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
