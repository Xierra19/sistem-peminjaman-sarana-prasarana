<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => array_filter(array_map('trim', explode(',', $role))))
            ->values()
            ->all();

        if ($allowedRoles === [] && $user->isAdmin()) {
            return $next($request);
        }

        if ($user->hasAnyRole($allowedRoles)) {
            return $next($request);
        }

        abort(403, 'Anda tidak memiliki akses ke modul ini.');
    }
}
