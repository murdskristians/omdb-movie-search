<?php

use App\Services\Omdb\OmdbException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi('60,1');

        // Behind a host such as Render, the https connection ends at the host's proxy, which passes
        // the request on as plain http and says so in the X-Forwarded-* headers. Without trusting
        // them, Laravel would write http:// addresses for the scripts and styles of an https page,
        // and browsers would refuse to load them.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Anything under /api answers with JSON, even without an Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            fn (OmdbException $e) => response()->json(['message' => $e->getMessage()], $e->status())
        );

        // Bad searches and rate limits are normal events. Only real failures belong in the log.
        $exceptions->dontReportWhen(
            fn (Throwable $e) => $e instanceof OmdbException && $e->status() < 500
        );
    })->create();
