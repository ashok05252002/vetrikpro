<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until a new employee has submitted their profile, the portal is the
 * onboarding page. Everyone else — including people who joined before
 * onboarding existed (status null) — passes straight through.
 */
class EnsureOnboardingComplete
{
    /** Routes an onboarding employee may still reach. */
    private const ALLOWED = ['onboarding.*', 'logout', 'invitation.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $status = $request->user()?->employee?->onboarding_status;

        if ($status !== null && $status->needsEmployee() && ! $request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Complete your profile first.'], 403)
                : redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
