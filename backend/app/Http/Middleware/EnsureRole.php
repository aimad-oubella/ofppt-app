<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active || ! in_array($user->role, $roles, true)) {
            abort(403, "Vous n'avez pas l'autorisation d'effectuer cette action.");
        }

        return $next($request);
    }
}
