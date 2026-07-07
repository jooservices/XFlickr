<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class SetUserPasswordCommandTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_command_updates_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $this->artisan('xflickr:user:password', [
            'email' => 'admin@local',
            '--password' => 'new-secure-password-1',
        ])
            ->assertSuccessful();

        $this->assertTrue(
            Hash::check('new-secure-password-1', User::query()->findOrFail($user->id)->password),
        );
    }

    public function test_command_rejects_default_password_in_production(): void
    {
        config(['app.env' => 'production']);

        User::factory()->create(['email' => 'admin@local']);

        $this->artisan('xflickr:user:password', [
            'email' => 'admin@local',
            '--password' => 'password',
        ])
            ->assertFailed();
    }
}
