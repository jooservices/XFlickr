<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;

/** @extends Factory<StorageAccount> */
class StorageAccountFactory extends Factory
{
    protected $model = StorageAccount::class;

    public function definition(): array
    {
        return [
            'provider' => StorageDriver::GooglePhotos->value,
            'label' => fake()->words(2, true),
            'credentials' => [
                'access_token' => fake()->sha256(),
                'refresh_token' => fake()->sha256(),
            ],
            'is_default' => false,
            'connected_at' => now(),
        ];
    }

    public function googlePhotos(): static
    {
        return $this->state(fn (): array => [
            'provider' => StorageDriver::GooglePhotos->value,
            'credentials' => [
                'access_token' => fake()->sha256(),
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GooglePhotos->defaultScopes(),
            ],
        ]);
    }

    public function googleDrive(): static
    {
        return $this->state(fn (): array => [
            'provider' => StorageDriver::GoogleDrive->value,
            'credentials' => [
                'access_token' => fake()->sha256(),
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::GoogleDrive->defaultScopes(),
            ],
        ]);
    }

    public function oneDrive(): static
    {
        return $this->state(fn (): array => [
            'provider' => StorageDriver::OneDrive->value,
            'credentials' => [
                'access_token' => fake()->sha256(),
                'refresh_token' => fake()->sha256(),
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->sha256(),
                'expires_at' => now()->addHour()->toIso8601String(),
                'granted_scopes' => StorageDriver::OneDrive->defaultScopes(),
            ],
        ]);
    }

    public function r2(): static
    {
        return $this->state(fn (): array => [
            'provider' => StorageDriver::R2->value,
            'credentials' => [
                'access_key_id' => fake()->regexify('[A-Z0-9]{20}'),
                'secret_access_key' => fake()->sha256(),
                'bucket' => fake()->slug(2),
                'endpoint' => 'https://'.fake()->domainName().'.r2.cloudflarestorage.com',
                'region' => 'auto',
                'prefix' => fake()->slug(1),
            ],
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
