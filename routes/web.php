<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Loaded by withRouting() in bootstrap/app.php and wrapped in the "web"
| middleware group. This is an API; the only web route is the stock welcome
| page, which tests/Feature/ExampleTest.php asserts still answers 200.
|
*/

Route::get('/', function () {
    return view('welcome');
});
