<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\TrustProxies::class);
        $middleware->alias([
            'quota' => \App\Http\Middleware\EnforceQuota::class,
        ]);

        // Laravel's default is redirectGuestsTo(fn () => route('login')), and
        // this application has no route named `login`. The auth middleware
        // resolves that redirect before the exception handler ever runs, so an
        // unauthenticated request that does not ask for JSON died with
        // "Route [login] not defined" - a 500 in place of a 401.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null; // let the handler answer 401 JSON
            }

            return Route::has('filament.admin.auth.login')
                ? route('filament.admin.auth.login')
                : null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This backend serves an API and a Filament panel, with no `login`
        // named route. Without this, an unauthenticated request to a protected
        // /api route that does not ask for JSON (a browser address bar, curl
        // with no Accept header) made the auth middleware try to redirect to
        // that missing route, answering 500 instead of 401.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 401);
            }
        });
    })->create();
