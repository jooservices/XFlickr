<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeders;

use App\Support\AdminCredentialResolver;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use Modules\Auth\Services\UserService;
use RuntimeException;

/**
 * Optional first-run admin account. Idempotent — skips when the email already exists.
 *
 * Invoke explicitly (not part of host DatabaseSeeder by default):
 *   php artisan db:seed --class=Modules\\Auth\\Database\\Seeders\\AdminUserSeeder
 *   php artisan module:seed Auth
 */
final class AdminUserSeeder extends Seeder
{
    public function run(AdminCredentialResolver $credentials, UserService $users): void
    {
        try {
            $password = $credentials->password();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            if ($this->command !== null) {
                $this->command->error($exception->getMessage());
            }

            throw $exception;
        }

        $users->ensureUser(
            $credentials->email(),
            'Admin',
            $password,
        );
    }
}
