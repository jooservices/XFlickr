<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Admin user seeding is Auth-owned and optional. Prefer an explicit class:
     *   php artisan db:seed --class=Modules\\Auth\\Database\\Seeders\\AdminUserSeeder
     * or module seed:
     *   php artisan module:seed Auth
     *
     * Operator shortcuts (`bash scripts/dev.sh seed`, Docker entrypoint, deploy
     * first boot) already pass that class when an admin account is desired.
     */
    public function run(): void
    {
        // Intentionally empty — do not auto-create admin on plain `db:seed`.
    }
}
