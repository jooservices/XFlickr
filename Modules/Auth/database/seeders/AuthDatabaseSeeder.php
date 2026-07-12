<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Auth module seeds. Admin user creation is optional — run this module seeder
 * (or AdminUserSeeder alone) when you want a first-run account.
 */
final class AuthDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);
    }
}
