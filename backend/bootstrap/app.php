<?php
/* BISM4RCK-KUN3H0 2026 */
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\Illuminate\Session\Middleware\StartSession::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
