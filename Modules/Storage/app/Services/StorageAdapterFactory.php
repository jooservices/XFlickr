<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Adapters\GoogleDriveAdapter;
use Modules\Storage\Adapters\GooglePhotosAdapter;
use Modules\Storage\Adapters\OneDriveAdapter;
use Modules\Storage\Adapters\R2Adapter;
use Modules\Storage\Contracts\StorageAdapter;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use Modules\Storage\Support\StorageApiLogger;

final class StorageAdapterFactory
{
    public function __construct(
        private readonly GoogleTokenService $googleTokens,
        private readonly MicrosoftTokenService $microsoftTokens,
        private readonly StorageFlysystemFactory $flysystem,
        private readonly OAuthConnectionChecks $oauthChecks,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    public function make(StorageAccount $account): StorageAdapter
    {
        $driver = StorageDriver::from($account->provider);

        return match ($driver) {
            StorageDriver::GooglePhotos => new GooglePhotosAdapter(
                $account,
                $this->googleTokens,
                $this->oauthChecks,
                $this->apiLogger,
            ),
            StorageDriver::GoogleDrive => new GoogleDriveAdapter(
                $account,
                $this->flysystem,
                $this->googleTokens,
                $this->oauthChecks,
                $this->apiLogger,
            ),
            StorageDriver::OneDrive => new OneDriveAdapter(
                $account,
                $this->flysystem,
                $this->microsoftTokens,
                $this->oauthChecks,
                $this->apiLogger,
            ),
            StorageDriver::R2 => new R2Adapter(
                $account,
                $this->flysystem,
            ),
        };
    }
}
