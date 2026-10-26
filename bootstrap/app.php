<?php

use App\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            require base_path('routes/settings.php');
        },
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);
        // Replaces statefulApi() so same-origin browser requests stay
        // authenticated even when the Referer header is stripped.
        $middleware->api(prepend: [EnsureFrontendRequestsAreStateful::class]);
        // Laravel Cloud terminates TLS at its proxy; without this, generated
        // URLs and redirects would use http:// and break secure cookies.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
