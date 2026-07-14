<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\R2;

use Aws\S3\S3Client;
use DateTimeImmutable;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\R2\BrowseService;
use Modules\Storage\Services\StorageFlysystemFactory;
use Tests\TestCase;

final class BrowseServiceTest extends TestCase
{
    public function test_browse_lists_folders_and_image_files(): void
    {
        $account = StorageAccount::factory()->r2()->make();
        $credentials = $account->credentials ?? [];
        $folderPrefix = ($credentials['prefix'] ?? '').'/album-one/';
        $imageKey = ($credentials['prefix'] ?? '').'/album-one/photo.jpg';

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('listObjectsV2')
            ->twice()
            ->andReturnUsing(function (array $params) use ($folderPrefix, $imageKey): array {
                if (isset($params['Delimiter'])) {
                    return [
                        'CommonPrefixes' => [['Prefix' => $folderPrefix]],
                        'NextContinuationToken' => 'album-next',
                    ];
                }

                return [
                    'Contents' => [
                        [
                            'Key' => $imageKey,
                            'Size' => 1024,
                            'LastModified' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
                        ],
                    ],
                ];
            });

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($client): void {
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: true,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame('album-one', $result->albums[0]['id']);
        $this->assertSame('album-next', $result->albumNextPageToken);
        $this->assertCount(1, $result->items);
        $this->assertSame('photo.jpg', $result->items[0]['name']);
        $this->assertSame('image/jpeg', $result->items[0]['mime_type']);
    }

    public function test_browse_uses_container_prefix_and_pagination_tokens(): void
    {
        $account = StorageAccount::factory()->r2()->make();
        $credentials = $account->credentials ?? [];
        $containerId = 'nested-folder';
        $expectedPrefix = ($credentials['prefix'] ?? '').'/'.$containerId.'/';

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('listObjectsV2')
            ->once()
            ->with(Mockery::on(function (array $params) use ($credentials, $expectedPrefix): bool {
                return $params['Bucket'] === $credentials['bucket']
                    && $params['Prefix'] === $expectedPrefix
                    && $params['ContinuationToken'] === 'item-page-2';
            }))
            ->andReturn([
                'Contents' => [[
                    'Key' => $expectedPrefix.'image.webp',
                    'Size' => 500,
                    'LastModified' => new DateTimeImmutable('2026-01-03T00:00:00+00:00'),
                ]],
                'NextContinuationToken' => 'item-page-3',
            ]);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($client): void {
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: 'item-page-2',
            containerId: $containerId,
            includeAlbums: false,
            includeItems: true,
        );

        $this->assertSame([], $result->albums);
        $this->assertCount(1, $result->items);
        $this->assertSame('image/webp', $result->items[0]['mime_type']);
        $this->assertSame('item-page-3', $result->itemNextPageToken);
    }

    public function test_browse_can_return_only_albums(): void
    {
        $account = StorageAccount::factory()->r2()->make();
        $credentials = $account->credentials ?? [];

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('listObjectsV2')->once()->andReturn(['CommonPrefixes' => []]);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($client): void {
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: false,
        );

        $this->assertSame([], $result->albums);
        $this->assertSame([], $result->items);
    }

    public function test_browse_skips_invalid_prefix_entries_and_non_image_files(): void
    {
        $account = StorageAccount::factory()->r2()->make();
        $credentials = $account->credentials ?? [];
        $prefix = $credentials['prefix'] ?? '';

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('listObjectsV2')
            ->twice()
            ->andReturnUsing(function (array $params) use ($prefix): array {
                if (isset($params['Delimiter'])) {
                    return [
                        'CommonPrefixes' => [
                            'bad-entry',
                            ['Prefix' => ''],
                            ['Prefix' => $prefix.'/valid-folder/'],
                        ],
                        'NextContinuationToken' => 'album-next',
                    ];
                }

                return [
                    'Contents' => [
                        'bad-entry',
                        ['Key' => $prefix.'/valid-folder/'],
                        ['Key' => $prefix.'/valid-folder/readme.txt', 'Size' => 10],
                        ['Key' => $prefix.'/valid-folder/photo.png', 'Size' => 20],
                    ],
                ];
            });

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($client): void {
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: 'album-page-1',
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: true,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame('album-next', $result->albumNextPageToken);
        $this->assertCount(2, $result->items);
        $this->assertSame('image/png', $result->items[1]['mime_type']);
    }

    public function test_verify_connection_delegates_to_flysystem_factory(): void
    {
        $credentials = StorageAccount::factory()->r2()->make()->credentials ?? [];

        $factory = Mockery::mock(StorageFlysystemFactory::class);
        $factory->shouldReceive('verifyR2Credentials')
            ->once()
            ->with($credentials);

        $this->instance(StorageFlysystemFactory::class, $factory);

        app(BrowseService::class)->verifyConnection($credentials);
    }
}
