<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless(($user?->isAdmin() ?? false) || in_array($user?->role, ['admin', 'staff'], true), 403);

        return $next($request);
    }
}
