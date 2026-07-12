<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Aws\S3\S3Client;
use Google\Service\Drive;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Justus\FlysystemOneDrive\OneDriveAdapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Visibility;
use Masbug\Flysystem\GoogleDriveAdapter;
use Microsoft\Graph\Graph;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageR2Config;
use RuntimeException;

final class StorageFlysystemFactory
{
    public function __construct(
        private readonly StorageGoogleTokenService $googleTokens,
        private readonly StorageMicrosoftTokenService $microsoftTokens,
    ) {}

    public function diskForAccount(StorageAccount $account): Filesystem
    {
        $credentials = $account->credentials ?? [];

        return match (StorageDriver::from($account->provider)) {
            StorageDriver::GoogleDrive => $this->googleDriveDisk($account, $credentials),
            StorageDriver::GooglePhotos => throw new RuntimeException('Google Photos uses direct API upload.'),
            StorageDriver::OneDrive => $this->oneDriveDisk($account, $credentials),
            StorageDriver::R2 => $this->r2Disk($credentials),
        };
    }

    public function r2Client(array $credentials): S3Client
    {
        $config = StorageR2Config::from($credentials);

        return new S3Client([
            'version' => 'latest',
            'region' => $config->region,
            'endpoint' => $config->endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $config->accessKeyId,
                'secret' => $config->secretAccessKey,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function r2Disk(array $credentials): Filesystem
    {
        $config = StorageR2Config::from($credentials);
        $client = $this->r2Client($credentials);

        $adapter = new AwsS3V3Adapter(
            $client,
            $config->bucket,
            '',
            new PortableVisibilityConverter(Visibility::PRIVATE),
        );

        return $this->filesystemFromAdapter($adapter);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function googleDriveDisk(StorageAccount $account, array $credentials): Filesystem
    {
        $client = $this->googleTokens->clientForAccount($credentials, $account);
        $service = new Drive($client);
        $adapter = new GoogleDriveAdapter($service, 'xflickr');

        return $this->filesystemFromAdapter($adapter);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function oneDriveDisk(StorageAccount $account, array $credentials): Filesystem
    {
        $accessToken = $this->microsoftTokens->accessToken($credentials, $account);

        $graph = new Graph;
        $graph->setAccessToken($accessToken);

        $adapter = new OneDriveAdapter($graph, 'me');

        return $this->filesystemFromAdapter($adapter);
    }

    private function filesystemFromAdapter(\League\Flysystem\FilesystemAdapter $adapter): Filesystem
    {
        $flysystem = new Flysystem($adapter);

        return new FilesystemAdapter($flysystem, $adapter, ['driver' => 'dynamic']);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function verifyR2Credentials(array $credentials): void
    {
        $config = StorageR2Config::from($credentials);
        $client = $this->r2Client($credentials);

        $client->headBucket([
            'Bucket' => $config->bucket,
        ]);
    }
}
