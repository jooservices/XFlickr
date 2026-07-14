<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use App\Models\User;
use App\Support\AdminCredentialResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Auth\Repositories\PasswordResetTokenRepository;
use Modules\Auth\Repositories\UserRepository;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $resetTokens,
        private readonly AdminCredentialResolver $credentials,
    ) {}

    /**
     * Create the user when missing (first-run / seeder). Does not overwrite an existing account.
     * Seeded / ensured users are always active.
     *
     * @throws InvalidArgumentException when the password is forbidden in production
     */
    public function ensureUser(string $email, string $name, string $password): User
    {
        $existing = $this->users->findByEmail($email);

        if ($existing !== null) {
            return $existing;
        }

        $this->credentials->assertPasswordAllowed($password);

        return $this->users->create($email, $name, $password, active: true);
    }

    /**
     * @throws InvalidArgumentException when the email is already registered or password is forbidden
     * @throws ValidationException when the password fails Laravel password rules
     */
    public function register(string $name, string $email, string $password, string $ip = '0.0.0.0'): User
    {
        $this->ensureNotRateLimited('register|'.$ip, 5);

        $this->credentials->assertPasswordAllowed($password);
        $this->assertPasswordRules($password);

        if ($this->users->findByEmail($email) !== null) {
            throw ValidationException::withMessages([
                'email' => __('A user with this email already exists.'),
            ]);
        }

        RateLimiter::clear('register|'.$ip);

        return $this->users->create($email, $name, $password, active: false);
    }

    /**
     * @return string|null Absolute reset URL when a token was created; null when no token (unknown/inactive)
     *
     * @throws ValidationException when rate limited
     */
    public function requestPasswordReset(string $email, string $ip = '0.0.0.0'): ?string
    {
        $this->ensureNotRateLimited('forgot-password|'.$ip.'|'.Str::lower($email), 5);

        $user = $this->users->findByEmail($email);

        if ($user === null || ! $user->is_active) {
            return null;
        }

        $plainToken = Str::random(64);
        $this->resetTokens->upsert($email, Hash::make($plainToken));

        $url = url('/reset-password/'.$plainToken.'?email='.urlencode($email));

        Log::info('Password reset token generated.', [
            'email' => $email,
        ]);

        RateLimiter::clear('forgot-password|'.$ip.'|'.Str::lower($email));

        return $url;
    }

    /**
     * @throws ValidationException when token/password invalid
     * @throws InvalidArgumentException when password forbidden in production
     */
    public function resetPasswordWithToken(string $email, string $token, string $password): void
    {
        $this->credentials->assertPasswordAllowed($password);
        $this->assertPasswordRules($password);

        $row = $this->resetTokens->findByEmail($email);

        if ($row === null || ! is_string($row->token ?? null) || ! Hash::check($token, (string) $row->token)) {
            throw ValidationException::withMessages([
                'email' => __('This password reset token is invalid.'),
            ]);
        }

        $expireMinutes = (int) config('auth.passwords.users.expire', 60);
        $createdAt = isset($row->created_at) ? Carbon::parse($row->created_at) : null;

        if ($createdAt === null || now()->subMinutes($expireMinutes)->greaterThan($createdAt)) {
            $this->resetTokens->deleteByEmail($email);

            throw ValidationException::withMessages([
                'email' => __('This password reset token has expired.'),
            ]);
        }

        $user = $this->users->findByEmail($email);

        if ($user === null || ! $user->is_active) {
            $this->resetTokens->deleteByEmail($email);

            throw ValidationException::withMessages([
                'email' => __('This password reset token is invalid.'),
            ]);
        }

        $this->users->updatePassword($user, $password);
        $this->resetTokens->deleteByEmail($email);
    }

    /**
     * @throws InvalidArgumentException when the user is missing
     */
    public function activateUser(string $email): User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new InvalidArgumentException("No user found with email [{$email}].");
        }

        if ($user->is_active) {
            return $user;
        }

        return $this->users->activate($user);
    }

    /**
     * @throws InvalidArgumentException when the password is forbidden in production or the user is missing
     * @throws ValidationException when the password fails Laravel password rules
     */
    public function resetPassword(string $email, string $password): User
    {
        $this->credentials->assertPasswordAllowed($password);
        $this->assertPasswordRules($password);

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new InvalidArgumentException("No user found with email [{$email}].");
        }

        return $this->users->updatePassword($user, $password);
    }

    /**
     * @throws InvalidArgumentException when a new password is forbidden in production
     * @throws ValidationException when a new password fails Laravel password rules
     */
    public function updateProfile(User $user, string $name, string $email, ?string $password = null): User
    {
        if ($password !== null && $password !== '') {
            $this->credentials->assertPasswordAllowed($password);
            $this->assertPasswordRules($password);
        }

        return $this->users->updateProfile($user, $name, $email, $password);
    }

    /**
     * @throws ValidationException
     */
    private function assertPasswordRules(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::defaults()]],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotRateLimited(string $key, int $maxAttempts): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            RateLimiter::hit($key);

            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
