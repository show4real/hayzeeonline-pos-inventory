<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the response contract the POS UI depends on.
 *
 * The Laravel 6 -> 12 upgrade rebuilt the middleware stack and the exception
 * handler from scratch (Http/Kernel and Exceptions/Handler no longer exist;
 * it is all bootstrap/app.php now). These assert the parts of that rebuild the
 * UI would notice: JSON rather than HTML, the error shapes the old handler
 * produced, and that protected routes are still protected.
 *
 * No database: an unauthenticated request is refused by jwt.auth before
 * anything touches a table.
 */
class ApiContractTest extends TestCase
{
    /**
     * Resolving the HTTP kernel is what applies the withMiddleware() block in
     * bootstrap/app.php to the router — the aliases and the group edits live
     * on a configuration object until then. A real request does this on the
     * way in; a test that only inspects the router has to ask for it.
     */
    private function router(): \Illuminate\Routing\Router
    {
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        return $this->app->make('router');
    }

    public function test_a_protected_route_refuses_an_anonymous_caller_with_json(): void
    {
        $response = $this->postJson('/api/users');

        $response->assertStatus(401)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['error']);
    }

    /**
     * The UI does not always send Accept: application/json. Laravel 6 decided
     * HTML-vs-JSON from that header, so without shouldRenderJsonWhen() these
     * callers would get an HTML error page — or a redirect to a login route
     * that does not exist here.
     */
    public function test_a_protected_route_answers_json_even_without_an_accept_header(): void
    {
        $response = $this->post('/api/users', [], ['Accept' => '*/*']);

        $response->assertStatus(401);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('content-type'));
        // "Token not provided" — no full stop — because tymon's middleware
        // answers before the JWTException handler in bootstrap/app.php does.
        // Same string jwt-auth 1.x produced, so the UI sees no change.
        $this->assertSame('Token not provided', $response->json('error'));
    }

    public function test_an_unknown_api_route_is_a_json_404(): void
    {
        $this->getJson('/api/no-such-endpoint')
            ->assertStatus(404)
            ->assertExactJson(['error' => 'Resource not found.']);
    }

    /**
     * A garbled bearer token must be a 401 the client can act on, not a 500.
     */
    public function test_a_malformed_token_is_rejected_as_json(): void
    {
        $this->postJson('/api/users', [], ['Authorization' => 'Bearer not-a-real-token'])
            ->assertStatus(401)
            ->assertJsonStructure(['error']);
    }

    /**
     * The login route is public: it must get past the auth middleware and be
     * refused on credentials instead. Reaching the controller means touching
     * the users table, so this asserts only that it is not an auth refusal.
     */
    public function test_the_login_route_is_not_behind_the_auth_middleware(): void
    {
        $routes = $this->router()->getRoutes();
        $login = $routes->getByAction(\App\Http\Controllers\Api\LoginController::class.'@login');

        $this->assertNotNull($login, 'the login route is not registered');
        $this->assertNotContains('jwt.auth', $login->gatherMiddleware());
    }

    /**
     * config/cors.php survived the removal of fruitcake/laravel-cors — the
     * framework's own HandleCors reads the same file. Without it the browser
     * blocks every call from the POS UI's origin.
     */
    public function test_cors_is_applied_to_api_routes(): void
    {
        $response = $this->call('OPTIONS', '/api/login', [], [], [], [
            'HTTP_ORIGIN' => 'https://pos.hayzeeonline.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $this->assertSame('*', $response->headers->get('access-control-allow-origin'));
    }

    /**
     * The old Http/Kernel put 'throttle:60,1' on the api group. Laravel 11
     * dropped that default, so it is set explicitly in bootstrap/app.php —
     * this is the assertion that catches it going missing again.
     */
    public function test_the_api_group_still_throttles_at_sixty_a_minute(): void
    {
        // gatherMiddleware() reports the group by name, so assert on the group
        // itself. This should stay equal to the old Http/Kernel 'api' group.
        $api = $this->router()->getMiddlewareGroups()['api'];

        $this->assertSame([
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ], $api);
    }

    /**
     * The aliases routes/api.php names must be registered — a missing one is
     * a 500 on every route in that group.
     */
    public function test_the_route_middleware_aliases_are_registered(): void
    {
        $aliases = $this->router()->getMiddleware();

        $this->assertSame(\App\Http\Middleware\CheckAdmin::class, $aliases['CheckAdmin'] ?? null);
        $this->assertSame(\Tymon\JWTAuth\Http\Middleware\Authenticate::class, $aliases['jwt.auth'] ?? null);
    }

    /**
     * An admin-only route carries both guards, in that order: authenticate
     * first, then check the admin flag. CheckAdmin calls auth()->user()->admin
     * with no null check, so it would fault if it ran first.
     */
    public function test_admin_routes_authenticate_before_checking_the_admin_flag(): void
    {
        $route = $this->router()->getRoutes()
            ->getByAction(\App\Http\Controllers\Api\UserController::class.'@index');

        $this->assertSame(['api', 'jwt.auth', 'CheckAdmin'], $route->gatherMiddleware());
    }
}
