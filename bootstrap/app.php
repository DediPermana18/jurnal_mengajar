<?php

use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\CheckPetugasPiket;
use App\Http\Middleware\EnsureWakaKesiswaan;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxy headers from Ngrok / Localhost.run / Cloudflare Tunnels
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'piket' => CheckPetugasPiket::class,
            'waka-kesiswaan' => EnsureWakaKesiswaan::class,
        ]);

        // Maintenance Mode: blokir semua web request bagi non-IT/QA saat aktif.
        $middleware->web(append: [
            CheckMaintenanceMode::class,
        ]);

        // Pastikan pengecekan Maintenance Mode jalan SEBELUM middleware 'auth'
        // (Authenticate berprioritas tinggi di Laravel). Tanpa ini, guest yang
        // membuka halaman ber-middleware 'auth' saat maintenance akan mendapat
        // redirect ke login (302) alih-alih halaman maintenance (503).
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            CheckMaintenanceMode::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
