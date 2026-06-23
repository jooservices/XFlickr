<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageAccount;
use App\Services\Storage\OAuth\MicrosoftProvider;
use Illuminate\Support\Str;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GoogleProvider;

final class StorageOAuthService
{
    public function __construct(
        private readonly StorageAccountService $accounts,
        private readonly SocialiteFactory $socialite,
    ) {}

    public function begin(StorageDriver $driver, ?int $accountId = null, ?string $returnUrl = null): string
    {
        $state = Str::random(40);
        session([
            'storage_oauth_state' => $state,
            'storage_oauth_provider' => $driver->value,
            'storage_oauth_account_id' => $accountId,
            'storage_oauth_return_url' => $returnUrl,
        ]);

        return $this->provider($driver)
            ->stateless()
            ->scopes($driver->defaultScopes())
            ->with(array_merge($this->providerOptions($driver), ['state' => $state]))
            ->redirect()
            ->getTargetUrl();
    }

    public function beginForAccount(StorageAccount $account, ?string $returnUrl = null): string
    {
        return $this->begin(StorageDriver::from($account->provider), $account->id, $returnUrl);
    }

    public function complete(string $provider, string $code): StorageAccount
    {
        $driver = StorageDriver::from($provider);
        $socialUser = $this->provider($driver)->stateless()->user();
        $appConfig = $this->appConfig($driver);

        $tokens = [
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
            'expires_in' => $socialUser->expiresIn,
            'token_type' => 'Bearer',
            'client_id' => $appConfig['client_id'] ?? null,
            'client_secret' => $appConfig['client_secret'] ?? null,
            'granted_scopes' => $driver->defaultScopes(),
        ];

        $accountId = session('storage_oauth_account_id');
        if (is_int($accountId) || (is_string($accountId) && ctype_digit($accountId))) {
            $existing = $this->accounts->find((int) $accountId);
            if ($existing !== null && $existing->provider === $driver->value) {
                $account = $this->accounts->reauthorize($existing, $tokens);
                $this->clearOAuthSession();

                return $account;
            }
        }

        $account = $this->accounts->connect(
            $driver->value,
            $socialUser->getEmail() ?? $socialUser->getId(),
            $tokens,
            $socialUser->getName(),
        );

        $this->clearOAuthSession();

        return $account;
    }

    public function consumeReturnUrl(): string
    {
        $returnUrl = session('storage_oauth_return_url');
        session()->forget('storage_oauth_return_url');

        return is_string($returnUrl) && $returnUrl !== '' ? $returnUrl : route('settings.index', ['tab' => 'storage']);
    }

    public function validateState(?string $state): bool
    {
        $expected = (string) session('storage_oauth_state', '');

        return $expected !== '' && hash_equals($expected, (string) $state);
    }

    private function clearOAuthSession(): void
    {
        session()->forget([
            'storage_oauth_state',
            'storage_oauth_provider',
            'storage_oauth_account_id',
        ]);
    }

    private function provider(StorageDriver $driver): AbstractProvider
    {
        $appConfig = $this->appConfig($driver);
        $redirect = $appConfig['redirect'] !== '' ? $appConfig['redirect'] : url("/storage/callback/{$driver->value}");

        if (! $this->socialite instanceof SocialiteManager) {
            throw new \RuntimeException('Socialite manager does not support explicit provider construction.');
        }

        return $this->socialite->buildProvider($this->providerClass($driver), [
            'client_id' => $appConfig['client_id'],
            'client_secret' => $appConfig['client_secret'],
            'redirect' => $redirect,
        ]);
    }

    /**
     * @return class-string<AbstractProvider>
     */
    private function providerClass(StorageDriver $driver): string
    {
        return match ($driver) {
            StorageDriver::GoogleDrive, StorageDriver::GooglePhotos => GoogleProvider::class,
            StorageDriver::OneDrive => MicrosoftProvider::class,
            StorageDriver::R2 => throw new \InvalidArgumentException("Storage driver [{$driver->value}] does not use OAuth."),
        };
    }

    /**
     * @return array<string, string>
     */
    private function appConfig(StorageDriver $driver): array
    {
        $path = "storage_app.{$driver->value}";
        if (! RuntimeConfig::has($path)) {
            throw new \RuntimeException("Storage app credentials for [{$driver->value}] are not configured.");
        }

        $value = RuntimeConfig::get($path);
        if (! is_array($value)) {
            throw new \RuntimeException("Storage app credentials for [{$driver->value}] are invalid.");
        }

        return [
            'client_id' => (string) ($value['client_id'] ?? $value['clientId'] ?? ''),
            'client_secret' => (string) ($value['client_secret'] ?? $value['clientSecret'] ?? ''),
            'redirect' => isset($value['redirect']) ? (string) $value['redirect'] : '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function providerOptions(StorageDriver $driver): array
    {
        return match ($driver) {
            StorageDriver::GoogleDrive, StorageDriver::GooglePhotos => [
                'access_type' => 'offline',
                'prompt' => 'consent',
            ],
            StorageDriver::OneDrive => [
                'prompt' => 'consent',
            ],
            StorageDriver::R2 => [],
        };
    }
}
