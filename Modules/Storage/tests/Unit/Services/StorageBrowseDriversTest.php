<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use DateTimeImmutable;
use Google\Client as GoogleClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GoogleDrive\BrowseService as GoogleDriveBrowseService;
use Modules\Storage\Services\R2\BrowseService as R2BrowseService;
use Modules\Storage\Services\StorageDriverRegistry;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageBrowseDriversTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_google_drive_browse_driver_returns_provider_folders_and_files(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $folderId = fake()->uuid();
        $fileId = fake()->uuid();

        $this->bindGoogleClient([
            [
                'files' => [[
                    'id' => $folderId,
                    'name' => fake()->words(2, true),
                    'mimeType' => 'application/vnd.google-apps.folder',
                ]],
            ],
            [
                'files' => [[
                    'id' => $fileId,
                    'name' => fake()->word().'.jpg',
                    'mimeType' => 'image/jpeg',
                    'size' => '1024',
                    'modifiedTime' => now()->toIso8601String(),
                ]],
            ],
        ]);

        $driver = app(StorageDriverRegistry::class)->browseDriver(StorageDriver::GoogleDrive);
        $this->assertInstanceOf(GoogleDriveBrowseService::class, $driver);

        $result = $driver->browse($account, 25, null, null, null, true, true);

        $this->assertCount(1, $result->albums);
        $this->assertCount(1, $result->items);
        $this->assertSame($folderId, $result->albums[0]['id'] ?? null);
        $this->assertSame($fileId, $result->items[0]['id'] ?? null);
    }

    public function test_r2_browse_driver_returns_folders_and_files(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $credentials = $account->credentials ?? [];
        $folderPrefix = ($credentials['prefix'] ?? '').'/album-one/';
        $imageKey = ($credentials['prefix'] ?? '').'/album-one/photo.jpg';

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('listObjectsV2')
            ->twice()
            ->andReturnUsing(function (array $params) use ($folderPrefix, $imageKey): array {
                if (isset($params['Delimiter'])) {
                    return ['CommonPrefixes' => [['Prefix' => $folderPrefix]]];
                }

                return [
                    'Contents' => [[
                        'Key' => $imageKey,
                        'Size' => 1024,
                        'LastModified' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
                    ]],
                ];
            });

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($client): void {
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $driver = app(StorageDriverRegistry::class)->browseDriver(StorageDriver::R2);
        $this->assertInstanceOf(R2BrowseService::class, $driver);

        $result = $driver->browse($account, 25, null, null, null, true, true);

        $this->assertCount(1, $result->albums);
        $this->assertCount(1, $result->items);
        $this->assertSame('album-one', $result->albums[0]['id'] ?? null);
        $this->assertSame('album-one/photo.jpg', $result->items[0]['id'] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     */
    private function bindGoogleClient(array $payloads): void
    {
        $responses = array_map(
            fn (array $payload): Response => new Response(
                200,
                ['Content-Type' => 'application/json'],
                (string) json_encode($payload, JSON_THROW_ON_ERROR),
            ),
            $payloads,
        );

        $handler = HandlerStack::create(new MockHandler($responses));
        $googleClient = new GoogleClient;
        $googleClient->setHttpClient(new GuzzleClient(['handler' => $handler]));
        $googleClient->setAccessToken([
            'access_token' => fake()->sha256(),
            'created' => time(),
            'expires_in' => 3600,
        ]);

        $this->mock(GoogleTokenService::class, function ($mock) use ($googleClient): void {
            $mock->shouldReceive('clientForAccount')->andReturn($googleClient);
        });
    }
}
