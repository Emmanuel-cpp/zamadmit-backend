<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
            ->withMiddleware(function (Middleware $middleware) {
            // Custom middleware aliases
            $middleware->alias([
                'role' => \App\Http\Middleware\RoleMiddleware::class,
                'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
                'admin.can' => \App\Http\Middleware\AdminCapability::class,
            ]);

            // API routes are stateless and use Sanctum token authentication.
            // No sessions, no CSRF — these only apply to traditional web routes.
            // This follows REST principles: each API request is self-authenticating.
            $middleware->validateCsrfTokens(except: [
                'api/*',
            ]);
        })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
