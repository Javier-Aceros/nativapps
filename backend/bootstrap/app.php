<?php

use App\Exceptions\AiProcessingException;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Transform AI failures into RFC 7807 Problem Details responses (HTTP 422).
        $exceptions->render(function (AiProcessingException $e, Request $request) {
            if ($request->expectsJson()) {
                $userMessages = [
                    'ai_summary_too_long' => 'El resumen generado por la IA superó el límite de caracteres permitido.',
                    'ai_error' => 'El servicio de IA no pudo procesar el contenido. Inténtalo de nuevo.',
                ];

                return response()->json([
                    'type' => 'https://httpstatuses.com/422',
                    'title' => 'AI Processing Error',
                    'status' => 422,
                    'detail' => $userMessages[$e->errorCode] ?? 'No se pudo procesar el mensaje con IA.',
                ], 422);
            }
        });
    })->create();
