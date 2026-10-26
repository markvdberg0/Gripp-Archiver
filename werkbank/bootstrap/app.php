<?php

use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias(['account' => EnsureAccountIsUsable::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Achter een reverse proxy (Docker, nginx, Caddy): vertrouw X-Forwarded-* van het interne netwerk.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
