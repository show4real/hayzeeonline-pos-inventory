<?php

// Laravel 11 moved the provider list out of config/app.php and into this file.
//
// The old skeleton registered four: AppServiceProvider, AuthServiceProvider,
// EventServiceProvider, RouteServiceProvider — plus a BroadcastServiceProvider
// that config/app.php left commented out. Only the first survives:
//
//   AuthServiceProvider   registered no policies, and registerPolicies() is
//                         automatic now.
//   EventServiceProvider  mapped Registered => SendEmailVerificationNotification,
//                         which the framework wires up itself since 11.x.
//   RouteServiceProvider  loaded routes/web.php and routes/api.php and applied
//                         the App\Http\Controllers namespace. The loading moved
//                         to withRouting() in bootstrap/app.php; the namespace
//                         is gone from the framework entirely, which is why the
//                         routes now name controllers by class.
//   BroadcastServiceProvider  routes/channels.php is loaded by withRouting().
return [
    App\Providers\AppServiceProvider::class,
];
