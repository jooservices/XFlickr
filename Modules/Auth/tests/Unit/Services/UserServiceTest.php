<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Unit\Services;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Auth\Services\UserService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    private UserService $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = app(UserService::class);
    }

    public function test_ensure_user_creates_when_missing(): void
    {
        $user = $this->users->ensureUser('ops@local', 'Ops', 'secure-password-1');

        $this->assertSame('ops@local', $user->email);
        $this->assertSame('Ops', $user->name);
        $this->assertTrue(Hash::check('secure-password-1', $user->fresh()->password));
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('users', ['email' => 'ops@local', 'is_active' => true]);
    }

    public function test_register_creates_inactive_user(): void
    {
        $user = $this->users->register('New User', 'new@local', 'secure-password-1');

        $this->assertFalse($user->is_active);
        $this->assertDatabaseHas('users', [
            'email' => 'new@local',
            'is_active' => 0,
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'new@local']);

        $this->expectException(ValidationException::class);

        $this->users->register('Other', 'new@local', 'secure-password-1');
    }

    public function test_activate_user_sets_active_flag(): void
    {
        User::factory()->inactive()->create(['email' => 'pending@local']);

        $user = $this->users->activateUser('pending@local');

        $this->assertTrue($user->is_active);
    }

    public function test_activate_user_rejects_missing_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->users->activateUser('missing@local');
    }

    public function test_request_password_reset_returns_url_for_active_user(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        Log::spy();

        $url = $this->users->requestPasswordReset('admin@local');

        $this->assertNotNull($url);
        $this->assertStringContainsString('/reset-password/', $url);
        $this->assertStringContainsString('email=admin%40local', $url);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'admin@local']);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                if ($message !== 'Password reset token generated.') {
                    return false;
                }

                $serialized = json_encode($context);

                return is_string($serialized)
                    && str_contains($serialized, 'admin@local')
                    && ! str_contains($serialized, 'reset-password')
                    && ! str_contains($serialized, 'reset_url');
            });
    }

    public function test_request_password_reset_returns_null_for_unknown_or_inactive(): void
    {
        User::factory()->inactive()->create(['email' => 'pending@local']);

        $this->assertNull($this->users->requestPasswordReset('ghost@local'));
        $this->assertNull($this->users->requestPasswordReset('pending@local'));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'pending@local']);
    }

    public function test_reset_password_with_token_updates_password_and_consumes_token(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $url = $this->users->requestPasswordReset('admin@local');
        $this->assertNotNull($url);

        preg_match('#/reset-password/([^?]+)#', $url, $matches);
        $token = $matches[1] ?? '';

        $this->users->resetPasswordWithToken('admin@local', $token, 'new-secure-password-1');

        $this->assertTrue(Hash::check('new-secure-password-1', User::query()->byEmail('admin@local')->firstOrFail()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'admin@local']);
    }

    public function test_reset_password_with_token_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'admin@local']);
        $this->users->requestPasswordReset('admin@local');

        $this->expectException(ValidationException::class);

        $this->users->resetPasswordWithToken('admin@local', 'not-the-token', 'new-secure-password-1');
    }

    public function test_ensure_user_returns_existing_without_overwriting(): void
    {
        $existing = User::factory()->create([
            'email' => 'ops@local',
            'name' => 'Original',
            'password' => 'original-password-1',
        ]);

        $result = $this->users->ensureUser('ops@local', 'Hacked Name', 'attacker-password-9');

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('Original', $result->fresh()->name);
        $this->assertTrue(Hash::check('original-password-1', $result->fresh()->password));
        $this->assertFalse(Hash::check('attacker-password-9', $result->fresh()->password));
    }

    public function test_ensure_user_rejects_default_password_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refusing to use the default admin password in production');

        $this->users->ensureUser(
            'admin@local',
            'Admin',
            AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD,
        );
    }

    public function test_ensure_user_allows_default_password_outside_production(): void
    {
        config(['app.env' => 'testing']);

        $user = $this->users->ensureUser(
            'admin@local',
            'Admin',
            AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD,
        );

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_updates_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'old-password-value',
        ]);

        $updated = $this->users->resetPassword('admin@local', 'new-secure-password-1');

        $this->assertSame($user->id, $updated->id);
        $this->assertTrue(Hash::check('new-secure-password-1', $updated->fresh()->password));
        $this->assertFalse(Hash::check('old-password-value', $updated->fresh()->password));
    }

    public function test_reset_password_rejects_missing_user(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No user found with email [missing@local].');

        $this->users->resetPassword('missing@local', 'new-secure-password-1');
    }

    public function test_reset_password_rejects_default_password_in_production(): void
    {
        config(['app.env' => 'production']);
        User::factory()->create(['email' => 'admin@local']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refusing to use the default admin password in production');

        $this->users->resetPassword(
            'admin@local',
            AdminCredentialResolver::FORBIDDEN_PRODUCTION_PASSWORD,
        );
    }

    public function test_reset_password_rejects_empty_password(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $this->expectException(ValidationException::class);

        $this->users->resetPassword('admin@local', '');
    }

    public function test_reset_password_rejects_too_short_password(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        try {
            $this->users->resetPassword('admin@local', 'short');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }
    }

    public function test_reset_password_is_case_sensitive_on_email_lookup(): void
    {
        User::factory()->create([
            'email' => 'Admin@Local',
            'password' => 'old-password-value',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->users->resetPassword('admin@local', 'new-secure-password-1');
    }

    public function test_reset_password_rejects_password_shorter_than_default_minimum(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $this->expectException(ValidationException::class);

        $this->users->resetPassword('admin@local', '       '); // 7 spaces
    }

    public function test_update_profile_updates_name_and_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Old',
            'email' => 'old@local',
            'password' => 'secure-password-1',
        ]);

        $updated = $this->users->updateProfile($user, 'New Name', 'new@local');

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new@local', $updated->email);
        $this->assertTrue(Hash::check('secure-password-1', $updated->fresh()->password));
    }

    public function test_update_profile_updates_password_when_provided(): void
    {
        $user = User::factory()->create([
            'password' => 'secure-password-1',
        ]);

        $updated = $this->users->updateProfile($user, $user->name, $user->email, 'secure-password-2');

        $this->assertTrue(Hash::check('secure-password-2', $updated->fresh()->password));
    }

    public function test_reset_password_with_token_rejects_expired_token(): void
    {
        User::factory()->create(['email' => 'admin@local']);

        $plainToken = 'expired-token-value';
        DB::table('password_reset_tokens')->insert([
            'email' => 'admin@local',
            'token' => Hash::make($plainToken),
            'created_at' => now()->subHours(3),
        ]);

        $this->expectException(ValidationException::class);

        $this->users->resetPasswordWithToken('admin@local', $plainToken, 'new-secure-password-1');
    }

    public function test_reset_password_with_token_rejects_inactive_user(): void
    {
        User::factory()->inactive()->create(['email' => 'pending@local']);

        $plainToken = 'inactive-token-value';
        DB::table('password_reset_tokens')->insert([
            'email' => 'pending@local',
            'token' => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->users->resetPasswordWithToken('pending@local', $plainToken, 'new-secure-password-1');
    }

    public function test_activate_user_returns_existing_when_already_active(): void
    {
        $user = User::factory()->create(['email' => 'active@local']);

        $result = $this->users->activateUser('active@local');

        $this->assertSame($user->id, $result->id);
        $this->assertTrue($result->is_active);
    }

    public function test_register_rate_limits_repeated_attempts_for_same_email(): void
    {
        $email = fake()->safeEmail();
        RateLimiter::clear('register|127.0.0.1');

        $this->users->register('First User', $email, 'secure-password-1', '127.0.0.1');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $this->users->register('Duplicate User', $email, 'secure-password-1', '127.0.0.1');
                $this->fail('Expected ValidationException for duplicate email');
            } catch (ValidationException) {
                // duplicate attempts still consume the rate limiter budget
            }
        }

        $this->expectException(ValidationException::class);

        $this->users->register('Throttled User', $email, 'secure-password-1', '127.0.0.1');
    }

    public function test_request_password_reset_rate_limits_repeated_attempts(): void
    {
        RateLimiter::clear('forgot-password|127.0.0.1|ghost@local');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->users->requestPasswordReset('ghost@local', '127.0.0.1');
        }

        $this->expectException(ValidationException::class);

        $this->users->requestPasswordReset('ghost@local', '127.0.0.1');
    }
}
