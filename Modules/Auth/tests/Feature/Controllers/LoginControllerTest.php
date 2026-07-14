<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature\Controllers;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Auth\Database\Seeders\AdminUserSeeder;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear($this->throttleKey('admin@local', '127.0.0.1'));
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect(route('dashboard'));
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

    public function test_users_can_authenticate_with_remember(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@local',
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull(auth()->user()?->getRememberToken());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@local',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->from('/login')->post('/login', []);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_rejects_sql_injection_shaped_email_as_invalid(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => "admin@local' OR '1'='1",
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_rate_limits_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => 'admin@local',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_guests_cannot_logout(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        User::factory()->inactive()->create([
            'email' => 'pending@local',
            'password' => 'password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'pending@local',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
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

    public function test_admin_user_seeder_is_idempotent(): void
    {
        config(['admin.password' => 'password']);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::query()->byEmail('admin@local')->count());
    }

    public function test_auth_database_seeder_creates_admin(): void
    {
        config(['admin.password' => 'password']);

        $this->seed(AuthDatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@local',
            'name' => 'Admin',
        ]);
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

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
