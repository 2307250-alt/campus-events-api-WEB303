<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Null-safe: a user with no role gives null here instead of crashing.
        $roleName = $user?->role?->name;

        if ($roleName === null || ! in_array($roleName, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
