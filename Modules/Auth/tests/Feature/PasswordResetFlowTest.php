<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Services\UserService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class PasswordResetFlowTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_forgot_password_flashes_reset_url_for_active_user(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'admin@local',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('resetUrl');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'admin@local']);
    }

    public function test_forgot_password_does_not_flash_url_for_inactive_user(): void
    {
        User::factory()->inactive()->create(['email' => 'pending@local']);

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'pending@local',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionMissing('resetUrl');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'pending@local']);
    }

    public function test_reset_password_form_can_be_rendered(): void
    {
        $this->get('/reset-password/example-token?email=admin@local')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'example-token')
                ->where('email', 'admin@local'));
    }

    public function test_users_can_reset_password_with_valid_token(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $url = app(UserService::class)->requestPasswordReset('admin@local');
        $this->assertNotNull($url);

        preg_match('#/reset-password/([^?]+)#', $url, $matches);
        $token = $matches[1] ?? '';

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin@local',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('new-secure-password-1', User::query()->byEmail('admin@local')->firstOrFail()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'admin@local']);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'admin@local']);
        app(UserService::class)->requestPasswordReset('admin@local');

        $this->from('/reset-password/bad-token')->post('/reset-password', [
            'token' => 'bad-token',
            'email' => 'admin@local',
            'password' => 'new-secure-password-1',
            'password_confirmation' => 'new-secure-password-1',
        ])->assertSessionHasErrors('email');
    }
}
