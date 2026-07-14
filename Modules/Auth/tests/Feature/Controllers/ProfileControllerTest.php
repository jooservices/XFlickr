<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_guests_are_redirected_from_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_profile_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Profile'));
    }

    public function test_users_can_update_name_without_current_password(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'password' => 'secure-password-1',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile', [
                'name' => 'New Name',
                'email' => $user->email,
                'password' => '',
                'current_password' => '',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_users_must_confirm_current_password_to_change_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@local',
            'password' => 'secure-password-1',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile', [
                'name' => $user->name,
                'email' => 'new@local',
                'password' => '',
                'current_password' => '',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertSame('old@local', $user->fresh()->email);
    }

    public function test_users_can_change_email_with_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'old@local',
            'password' => 'secure-password-1',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile', [
                'name' => $user->name,
                'email' => 'new@local',
                'password' => '',
                'current_password' => 'secure-password-1',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('new@local', $user->fresh()->email);
    }

    public function test_users_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'secure-password-1',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'secure-password-2',
                'current_password' => 'secure-password-1',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('secure-password-2', $user->fresh()->password));
    }

    public function test_profile_rejects_duplicate_email(): void
    {
        $other = User::factory()->create(['email' => 'taken@local']);
        $user = User::factory()->create([
            'email' => 'mine@local',
            'password' => 'secure-password-1',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->put('/profile', [
                'name' => $user->name,
                'email' => $other->email,
                'password' => '',
                'current_password' => 'secure-password-1',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('mine@local', $user->fresh()->email);
    }
}
