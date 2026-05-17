<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow request only when authenticated user has one of the given roles.
     *
     * Usage: ->middleware('role:admin,mentor')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $roleList) => explode(',', $roleList))
            ->map(fn (string $role) => trim(strtolower($role)))
            ->filter()
            ->flatMap(function (string $role) {
                if ($role === User::ROLE_ADMIN) {
                    return User::adminRoles();
                }

                return [$role];
            })
            ->unique()
            ->values()
            ->all();

        if (empty($allowedRoles)) {
            return $next($request);
        }

        if ($user->hasRole($allowedRoles)) {
            return $next($request);
        }

        return redirect('/')->with('message', 'ACCESS_DENIED: INSUFFICIENT_ROLE');
    }
}
