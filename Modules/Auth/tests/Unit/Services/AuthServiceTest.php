<?php

declare(strict_types=1);

namespace Modules\Auth\Tests\Unit\Services;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\AuthService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class AuthServiceTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    private AuthService $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = app(AuthService::class);
        RateLimiter::clear($this->throttleKey('admin@local', '127.0.0.1'));
    }

    public function test_login_authenticates_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $request = $this->loginRequest('admin@local', 'password');

        $this->auth->login($request, 'admin@local', 'password', false);

        $this->assertAuthenticatedAs(User::query()->byEmail('admin@local')->firstOrFail());
    }

    public function test_login_with_remember_flag(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $request = $this->loginRequest('admin@local', 'password');

        $this->auth->login($request, 'admin@local', 'password', true);

        $this->assertAuthenticated();
        $this->assertNotNull(Auth::user()?->getRememberToken());
    }

    public function test_login_rejects_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'email' => 'pending@local',
            'password' => 'password',
        ]);

        $request = $this->loginRequest('pending@local', 'password');

        try {
            $this->auth->login($request, 'pending@local', 'password', false);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertGuest();
            $this->assertSame(
                'Account pending admin activation.',
                $exception->errors()['email'][0] ?? null,
            );
        }
    }

    public function test_login_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $request = $this->loginRequest('admin@local', 'wrong-password');

        try {
            $this->auth->login($request, 'admin@local', 'wrong-password', false);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertGuest();
            $this->assertSame(__('auth.failed'), $exception->errors()['email'][0] ?? null);
        }
    }

    public function test_login_rejects_unknown_email(): void
    {
        $request = $this->loginRequest('ghost@local', 'password');

        $this->expectException(ValidationException::class);

        $this->auth->login($request, 'ghost@local', 'password', false);
    }

    public function test_failed_login_increments_rate_limiter(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $key = $this->throttleKey('admin@local', '127.0.0.1');
        $this->assertSame(0, RateLimiter::attempts($key));

        try {
            $this->auth->login($this->loginRequest('admin@local', 'bad'), 'admin@local', 'bad', false);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_successful_login_clears_rate_limiter(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $key = $this->throttleKey('admin@local', '127.0.0.1');
        RateLimiter::hit($key);
        RateLimiter::hit($key);

        $this->auth->login($this->loginRequest('admin@local', 'password'), 'admin@local', 'password', false);

        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_login_locks_out_after_five_failures(): void
    {
        Event::fake([Lockout::class]);

        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $key = $this->throttleKey('admin@local', '127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->auth->login($this->loginRequest('admin@local', 'bad'), 'admin@local', 'bad', false);
            } catch (ValidationException) {
                // expected failures
            }
        }

        $this->assertSame(5, RateLimiter::attempts($key));

        try {
            $this->auth->login($this->loginRequest('admin@local', 'password'), 'admin@local', 'password', false);
            $this->fail('Expected lockout ValidationException');
        } catch (ValidationException $exception) {
            $this->assertGuest();
            $this->assertStringContainsString('seconds', strtolower($exception->errors()['email'][0] ?? ''));
            Event::assertDispatched(Lockout::class);
        }
    }

    public function test_throttle_key_normalizes_email_case(): void
    {
        User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $mixedCaseKey = $this->throttleKey('Admin@Local', '127.0.0.1');
        $lowerKey = $this->throttleKey('admin@local', '127.0.0.1');
        $this->assertSame($lowerKey, $mixedCaseKey);

        try {
            $this->auth->login($this->loginRequest('Admin@Local', 'bad'), 'Admin@Local', 'bad', false);
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(1, RateLimiter::attempts($lowerKey));
    }

    public function test_logout_ends_session_and_clears_auth(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@local',
            'password' => 'password',
        ]);

        $this->actingAs($user);
        $this->assertAuthenticated();

        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('probe', 'value');

        $this->auth->logout($request->session());

        $this->assertGuest();
        $this->assertFalse($request->session()->has('probe'));
    }

    private function loginRequest(string $email, string $password): Request
    {
        $request = Request::create('/login', 'POST', [
            'email' => $email,
            'password' => $password,
        ], server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setLaravelSession($this->app['session']->driver());

        return $request;
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
