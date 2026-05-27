<?php

use App\Http\Middleware\EnsureUserIsBuyer;
use App\Http\Middleware\EnsureUserIsOrganizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'organizer' => EnsureUserIsOrganizer::class,
            'user' => EnsureUserIsBuyer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $forbiddenJson = function (\Throwable $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Forbidden.',
                'errors' => (object) [],
            ], Response::HTTP_FORBIDDEN);
        };

        $exceptions->render(fn (AuthorizationException $exception, Request $request) => $forbiddenJson($exception, $request));
        $exceptions->render(fn (AccessDeniedHttpException $exception, Request $request) => $forbiddenJson($exception, $request));
    })->create();
