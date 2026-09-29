<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureUserIsActive::class,
            EnsureOnboardingComplete::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        /*
         * Friendly error pages (resources/js/pages/error.tsx) instead of bare
         * framework ones. While debugging, crashes (500/503) keep Laravel's
         * detailed page; JSON requests keep JSON; an expired form (419) goes
         * back with a message rather than to a dead end.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if (in_array($status, [500, 503], true) && app()->hasDebugModeEnabled()) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'That page had been open too long, so nothing was saved. Please try again.');
            }

            // A reason given with abort(403, '…') is written for people; show it.
            $message = $status === 403 && $e instanceof HttpExceptionInterface && $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                ? $e->getMessage()
                : null;

            return Inertia::render('error', ['status' => $status, 'message' => $message])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
