<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Uso en rutas: ->middleware('role:admin,tecnico')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user && in_array($user->role, array_map(fn (string $role) => UserRole::from($role), $roles), true),
            403,
            'No tienes permisos para acceder a esta sección.'
        );

        return $next($request);
    }
}
