<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\IgnoresAuthentication;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAuthenticateForFeatureTests()) {
            $this->authenticateAsAdmin();
        }
    }

    protected function shouldAuthenticateForFeatureTests(): bool
    {
        if (! in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            return false;
        }

        return ! in_array(IgnoresAuthentication::class, class_uses_recursive(static::class), true);
    }

    protected function authenticateAsAdmin(): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@local'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ],
        );

        $this->actingAs($user);

        return $user;
    }
}
