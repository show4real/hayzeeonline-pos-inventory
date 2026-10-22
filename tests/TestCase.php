<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // The CreatesApplication trait is gone: since Laravel 11 the base
    // TestCase bootstraps the application from bootstrap/app.php itself.
}
