<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  One or more roles that are allowed access
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $userRole = auth()->user()->role;

        // Super admin bypasses all role checks
        if ($userRole === 'super_admin') {
            return $next($request);
        }

        if (! in_array($userRole, $roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
