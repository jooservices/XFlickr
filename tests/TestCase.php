<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function loadFixture(string $relativePath): array
    {
        $path = base_path('tests/Fixtures/'.$relativePath);
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Fixture not found: {$relativePath}");
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException("Fixture must decode to array: {$relativePath}");
        }

        return $decoded;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAuthenticateForFeatureTests()) {
            $this->authenticateAsAdmin();
        }
    }

    protected function shouldAuthenticateForFeatureTests(): bool
    {
        if (! in_array(SafeRefreshDatabase::class, class_uses_recursive(static::class), true)) {
            return false;
        }

        return ! in_array(IgnoresAuthentication::class, class_uses_recursive(static::class), true);
    }

    protected function authenticateAsAdmin(): User
    {
        $email = (string) config('admin.email', 'admin@local');
        $password = (string) config('admin.password', 'password');

        $user = User::factory()->create([
            'email' => $email,
            'password' => $password,
        ]);

        $this->actingAs($user);

        return $user;
    }
}
