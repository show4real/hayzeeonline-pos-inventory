<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * routes/web.php serves the stock welcome view at /.
     *
     * RefreshDatabase is deliberately not used: this route touches no tables,
     * and the migrations in this repository are a partial record of a schema
     * that is maintained by hand, so migrating a scratch database is not a
     * reliable way to set a test up here.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
    }

    /**
     * The health endpoint withRouting(health: '/up') adds. docker/deploy.sh
     * polls it to decide whether a release came up, so a change that breaks
     * it would roll every future deploy back.
     */
    public function test_the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertStatus(200);
    }
}
