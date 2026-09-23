<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->role === $role, 403, 'This area is not available for your account.');

        return $next($request);
    }
}
