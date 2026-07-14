<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature\Controllers;

use App\Models\User;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ForgotPasswordControllerTest extends TestCase
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
}
