<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'logout',
            'logout/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('logout') || $request->is('logout/*')) {
                return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
            }
            return redirect()->route('login')->with('info', 'Sesi Anda telah kedaluwarsa. Silakan masuk kembali.');
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419) {
                if ($request->is('logout') || $request->is('logout/*')) {
                    return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
                }
                return redirect()->route('login')->with('info', 'Sesi Anda telah kedaluwarsa. Silakan masuk kembali.');
            }
        });
    })->create();