<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Database\Seeders\AdminUserSeeder;
use InvalidArgumentException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'admin@local',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $this->actingAs($user);

        $response = $this->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_admin_user_seeder_creates_default_account(): void
    {
        config(['admin.password' => 'password']);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@local',
            'name' => 'Admin',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_admin_user_seeder_rejects_default_password_in_production(): void
    {
        config([
            'app.env' => 'production',
            'admin.password' => AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->seed(AdminUserSeeder::class);
    }
}
