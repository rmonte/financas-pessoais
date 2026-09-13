<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seed Admin Credentials
    |--------------------------------------------------------------------------
    |
    | Used by `php artisan db:seed` to create (or find) the demo admin user.
    | Override these in your local .env if you want a specific login.
    |
    */

    'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'),

    'admin_password' => env('SEED_ADMIN_PASSWORD', 'password'),

];
