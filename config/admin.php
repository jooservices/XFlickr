<?php

declare(strict_types=1);

return [
    'email' => env('ADMIN_EMAIL', 'admin@local'),

    /*
    |--------------------------------------------------------------------------
    | Initial admin password
    |--------------------------------------------------------------------------
    |
    | Used by Modules\\Auth\\Database\\Seeders\\AdminUserSeeder on first install
    | when an operator opts in (dev.sh seed, Docker entrypoint, deploy first boot,
    | or `php artisan module:seed Auth`). In production, the literal "password" is
    | rejected. Docker entrypoint generates a random value when unset and prints
    | it once to the container log.
    |
    */
    'password' => env('ADMIN_PASSWORD'),
];
