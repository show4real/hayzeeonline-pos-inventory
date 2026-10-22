<?php

use App\Http\Middleware\CheckAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // In production the stack sits behind Apache, which terminates TLS.
        // Without this Laravel builds http:// URLs for an https:// site and
        // rate-limits every caller as though they shared the proxy's address.
        //
        // Trusting '*' is safe only because the app port is bound to
        // localhost, so that proxy is the sole thing that can reach it.
        // Replaces the old App\Http\Middleware\TrustProxies, which extended
        // fideloper/proxy — that package is now part of the framework.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Carried over verbatim from the old Http/Kernel "api" group, which
        // had 'throttle:60,1'. Laravel 11+ no longer throttles the api group
        // by default, so without this line the limit silently disappears.
        $middleware->throttleApi('60,1');

        // The only alias the old Kernel defined that is still referenced by
        // routes/api.php. 'jwt.auth' comes from tymon/jwt-auth, which the
        // package registers itself.
        $middleware->alias([
            'CheckAdmin' => CheckAdmin::class,
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every route here is an API route — never redirect to a login page
        // or hand a JSON client an HTML error page. Laravel 6 decided this
        // from the Accept header, which the POS UI does not always send.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => true);

        $exceptions->render(fn (AuthenticationException $e) => response()->json(
            ['error' => 'Unauthenticated.'], 401
        ));

        // CheckAdmin answers with {"error": "..."} and a 403, so keep every
        // permission failure on that one shape.
        $exceptions->render(fn (AuthorizationException $e) => response()->json(
            ['error' => $e->getMessage() !== 'This action is unauthorized.'
                ? $e->getMessage()
                : "You don't have sufficient permission to access this resource"],
            403
        ));

        // The controllers return the bare message bag on 422
        // (`response()->json($validator->messages(), 422)`). Laravel 12 would
        // wrap a thrown ValidationException as {"message":…,"errors":{…}},
        // so anything that throws rather than checking $validator->fails()
        // would come back in a shape the UI does not parse. Keep one shape.
        $exceptions->render(fn (ValidationException $e) => response()->json(
            $e->errors(), 422
        ));

        // Route model binding (Category $category, User $user, …) throws this
        // when the id does not exist. Laravel 6 turned it into a 404 HTML
        // page; the UI needs JSON.
        $exceptions->render(fn (ModelNotFoundException $e) => response()->json(
            ['error' => 'Resource not found.'], 404
        ));

        $exceptions->render(fn (NotFoundHttpException $e) => response()->json(
            ['error' => 'Resource not found.'], 404
        ));

        // jwt-auth signals a missing, expired or garbled token by throwing.
        // Without these the client gets a stack trace instead of a 401 it can
        // act on by refreshing or signing in again.
        $exceptions->render(fn (TokenExpiredException $e) => response()->json(
            ['error' => 'Token expired.'], 401
        ));

        $exceptions->render(fn (TokenInvalidException $e) => response()->json(
            ['error' => 'Token invalid.'], 401
        ));

        $exceptions->render(fn (JWTException $e) => response()->json(
            ['error' => 'Token not provided.'], 401
        ));

        $exceptions->render(fn (UnauthorizedHttpException $e) => response()->json(
            ['error' => $e->getMessage() ?: 'Unauthenticated.'], 401
        ));
    })->create();
