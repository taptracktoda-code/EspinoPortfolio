<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RailwayIngress;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
        if (env('RAILWAY_ENVIRONMENT_ID')) {
            $middleware->prepend(RailwayIngress::class);
            $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            if (request()->is('admin', 'admin/*', 'contact', 'contact/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }

            return $response;
        });
    })->create();
