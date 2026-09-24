<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stricter than `manages-people`: organisation settings are the administrator's
 * alone, not HR's.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Only an administrator can change organisation settings.');
        }

        return $next($request);
    }
}
