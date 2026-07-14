<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Services\UserService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ResetPasswordControllerTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

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
