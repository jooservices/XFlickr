<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ResetPasswordCommandTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_command_updates_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'admin@local',
            '--password' => 'new-secure-password-1',
        ])
            ->expectsOutputToContain('Password updated for [admin@local].')
            ->assertSuccessful();

        $this->assertTrue(
            Hash::check('new-secure-password-1', User::query()->findOrFail($user->id)->password),
        );
    }

    public function test_command_rejects_default_password_in_production(): void
    {
        config(['app.env' => 'production']);

        User::factory()->create(['email' => 'admin@local']);

        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'admin@local',
            '--password' => AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD,
        ])
            ->expectsOutputToContain('Refusing to use the default admin password in production')
            ->assertFailed();
    }

    public function test_command_rejects_missing_user(): void
    {
        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'missing@local',
            '--password' => 'new-secure-password-1',
        ])
            ->expectsOutputToContain('No user found with email [missing@local].')
            ->assertFailed();
    }

    public function test_command_rejects_short_password(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'admin@local',
            '--password' => 'short',
        ])
            ->assertFailed();
    }

    public function test_command_prompts_and_rejects_mismatched_confirmation(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'admin@local',
        ])
            ->expectsQuestion('New password', 'new-secure-password-1')
            ->expectsQuestion('Confirm password', 'different-password-1')
            ->expectsOutputToContain('Passwords do not match.')
            ->assertFailed();
    }

    public function test_command_prompts_and_updates_when_confirmation_matches(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $this->artisan('xflickr:auth:reset-password', [
            'email' => 'admin@local',
        ])
            ->expectsQuestion('New password', 'new-secure-password-1')
            ->expectsQuestion('Confirm password', 'new-secure-password-1')
            ->assertSuccessful();

        $this->assertTrue(
            Hash::check('new-secure-password-1', User::query()->findOrFail($user->id)->password),
        );
    }
}
