<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception): Response {
            if (request()->is('api/*')) {
                return response()->json([
                    'code' => 'AUTH_REQUIRED',
                    'message' => '未登录或登录已失效。',
                ], 401);
            }

            return response($exception->getMessage(), 401);
        });

        $exceptions->render(function (\RuntimeException $exception): Response {
            if (! request()->is('api/*')) {
                return response($exception->getMessage(), 500);
            }

            $message = $exception->getMessage();
            $code = 'SERVER_ERROR';
            $status = 500;

            if (str_contains($message, 'Admission denied') || str_contains($message, 'ResourceExhausted')) {
                $code = 'MODEL_OVERLOADED';
                $message = '当前模型负载过高，请稍后重试。';
                $status = 503;
            } elseif (str_contains($message, 'Request Entity Too Large')) {
                $code = 'REQUEST_TOO_LARGE';
                $message = '上下文内容过长，请新建对话或缩短输入。';
                $status = 413;
            }

            return response()->json([
                'code' => $code,
                'message' => $message,
            ], $status);
        });
    })->create();
