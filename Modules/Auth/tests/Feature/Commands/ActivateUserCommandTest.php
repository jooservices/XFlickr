<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature\Commands;

use App\Models\User;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ActivateUserCommandTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_activate_user_command_allows_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'pending@local',
            'password' => 'secure-password-1',
        ]);

        $this->artisan('xflickr:auth:activate-user', ['email' => 'pending@local'])
            ->assertSuccessful();

        $this->post('/login', [
            'email' => 'pending@local',
            'password' => 'secure-password-1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_activate_user_command_fails_for_missing_user(): void
    {
        $this->artisan('xflickr:auth:activate-user', ['email' => 'missing@local'])
            ->assertFailed();
    }
}
