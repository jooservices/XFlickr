<?php

declare(strict_types=1);

return [
    'email' => env('ADMIN_EMAIL', 'admin@local'),

    /*
    |--------------------------------------------------------------------------
    | Initial admin password
    |--------------------------------------------------------------------------
    |
    | Used by AdminUserSeeder on first install. In production, the literal
    | "password" is rejected. Docker entrypoint generates a random value when
    | unset and prints it once to the container log.
    |
    */
    'password' => env('ADMIN_PASSWORD'),
];
