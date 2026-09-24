<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the /admin area to users who may manage people (admin, HR).
 */
class EnsureUserManagesPeople
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->managesPeople()) {
            abort(403, 'You do not have access to the administration area.');
        }

        return $next($request);
    }
}
