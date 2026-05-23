<?php

use App\Http\Middleware\AuthenticateRelayClient;
use App\Http\Middleware\AuthenticateRelayHub;
use App\Http\Middleware\EnsureRelayOperatorIsActive;
use App\Http\Middleware\NegotiateRelayProtocolVersion;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'relay.client' => AuthenticateRelayClient::class,
            'relay.hub' => AuthenticateRelayHub::class,
            'relay.protocol' => NegotiateRelayProtocolVersion::class,
            'relay.operator' => EnsureRelayOperatorIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->expectsJson() || $request->wantsJson() || $request->isXmlHttpRequest()) {
                return response()->json([
                    'status' => false,
                    'data' => null,
                    'meta' => null,
                    'error' => [
                        'message' => 'Session expired.',
                    ],
                    'session_expired' => true,
                ], 401);
            }
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson() || $request->wantsJson() || $request->isXmlHttpRequest()) {
                return response()->json([
                    'status' => false,
                    'data' => null,
                    'meta' => null,
                    'error' => [
                        'message' => 'Page expired.',
                    ],
                    'session_expired' => true,
                ], 419);
            }
        });
    })->create();
