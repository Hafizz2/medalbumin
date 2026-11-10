<?php

use App\Http\Middleware\EnsureActiveStudent;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\SetContentLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'student' => EnsureActiveStudent::class,
            'content.locale' => SetContentLocale::class,
            'staff' => EnsureStaff::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
        // Staff sign in at /admin/login, students at /account/login (same session guard).
        $middleware->redirectGuestsTo(fn (Request $r) => match (true) {
            $r->is('api/*') => null,
            $r->is('admin', 'admin/*') => route('login'),
            default => route('account.login'),
        });
        $middleware->redirectUsersTo(fn (Request $r) => $r->user()?->is_staff ? '/admin' : '/account');
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());

        // Consistent, friendly API errors.
        $exceptions->render(function (NotFoundHttpException $e, Request $r) {
            if ($r->is('api/*')) {
                return response()->json(['message' => $e->getPrevious() instanceof ModelNotFoundException ? 'Not found.' : ($e->getMessage() ?: 'Not found.')], 404);
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $r) {
            if ($r->is('api/*')) {
                return response()->json(['message' => 'Please sign in again.', 'code' => 'UNAUTHENTICATED'], 401);
            }
        });
    })->create();
