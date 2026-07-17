<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The public status JSON endpoint always answers with the error envelope,
        // whatever goes wrong, so machine clients get a stable contract.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('status/*/json')) {
                return null;
            }

            // Exceptions that already carry their own response (e.g. the throttle
            // 429, which is a JSON envelope) pass straight through.
            if ($e instanceof HttpResponseException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            [$code, $message] = match ($status) {
                404 => ['not_found', 'Status page not found.'],
                429 => ['rate_limited', 'Too many requests.'],
                default => ['server_error', 'Something went wrong.'],
            };

            return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
        });
    })->create();
