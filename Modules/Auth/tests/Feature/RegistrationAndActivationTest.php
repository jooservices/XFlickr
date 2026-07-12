<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class RegistrationAndActivationTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_register_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_users_can_register_but_cannot_login_until_activated(): void
    {
        $response = $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@local',
            'password' => 'secure-password-1',
            'password_confirmation' => 'secure-password-1',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('users', [
            'email' => 'pending@local',
            'is_active' => 0,
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'pending@local',
            'password' => 'secure-password-1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->artisan('xflickr:auth:activate-user', ['email' => 'pending@local'])
            ->assertSuccessful();

        $this->post('/login', [
            'email' => 'pending@local',
            'password' => 'secure-password-1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@local']);

        $this->from('/register')->post('/register', [
            'name' => 'Other',
            'email' => 'taken@local',
            'password' => 'secure-password-1',
            'password_confirmation' => 'secure-password-1',
        ])->assertSessionHasErrors('email');
    }

    public function test_activate_user_command_fails_for_missing_user(): void
    {
        $this->artisan('xflickr:auth:activate-user', ['email' => 'missing@local'])
            ->assertFailed();
    }
}
