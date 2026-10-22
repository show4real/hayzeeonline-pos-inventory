<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | "api" is the default guard, not "web": every route in this application is
    | a stateless JWT endpoint, and the controllers call auth()->attempt(),
    | auth()->user() and auth()->factory() with no guard argument.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | The "jwt" driver comes from tymon/jwt-auth, which registers it itself.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | App\User, not App\Models\User: the models in this application live in the
    | App namespace directly (app/User.php, app/Product.php, …). Laravel 8
    | moved them to app/Models in new skeletons, but moving them here would
    | mean touching every controller and every relationship string such as
    | $this->belongsTo('App\Branch') — a large diff for no behavioural gain.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\User::class),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | The table is 'password_resets', the Laravel 6 name. Laravel 11 renamed
    | its default to 'password_reset_tokens'; this database has the old table,
    | so the old name stays.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_resets'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */

    'password_timeout' => (int) env('AUTH_PASSWORD_TIMEOUT', 10800),

];
