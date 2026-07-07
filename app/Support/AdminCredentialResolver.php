<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;

final class AdminCredentialResolver
{
    public const FORBIDDEN_PRODUCTION_PASSWORD = 'password';

    public function email(): string
    {
        $email = config('admin.email');

        return is_string($email) && $email !== '' ? $email : 'admin@local';
    }

    /**
     * Resolve the plaintext password for seeding or validation.
     *
     * @throws RuntimeException when no password is configured outside local/testing
     * @throws InvalidArgumentException when production uses the forbidden default
     */
    public function password(): string
    {
        $password = config('admin.password');

        if (! is_string($password) || $password === '') {
            if ($this->allowsGeneratedLocalPassword()) {
                return self::FORBIDDEN_PRODUCTION_PASSWORD;
            }

            throw new RuntimeException(
                'ADMIN_PASSWORD is not set. Set ADMIN_PASSWORD in .env or export it before seeding the admin user.',
            );
        }

        $this->assertPasswordAllowed($password);

        return $password;
    }

    public function assertPasswordAllowed(string $password): void
    {
        if ($this->isProduction() && $password === self::FORBIDDEN_PRODUCTION_PASSWORD) {
            throw new InvalidArgumentException(
                'Refusing to use the default admin password in production. Set a strong ADMIN_PASSWORD.',
            );
        }
    }

    private function allowsGeneratedLocalPassword(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true);
    }

    private function isProduction(): bool
    {
        return config('app.env') === 'production';
    }
}
