<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {

        // alias middleware ROLE
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);

    })

    ->withExceptions(function (Exceptions $exceptions) {
      $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
    $pesan = ['title' => 'Sesi habis', 'errors' => ['Halaman sudah kedaluwarsa. Refresh halaman, lalu ulangi input.']];

    return $request->expectsJson()
        ? response()->json(['success' => false] + $pesan, 419)
        : back()->withInput()->with('save_error', $pesan);
});
    })
    ->create();