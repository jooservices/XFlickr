<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Aws\S3\S3Client;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\StorageUploadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageUploadServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_upload_stream_delegates_to_google_photos_and_caches_item(): void
    {
        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'mediaItem' => [
                        'id' => 'media-'.fake()->uuid(),
                        'filename' => 'photo.jpg',
                        'mimeType' => 'image/jpeg',
                        'baseUrl' => 'https://example.com/photo',
                        'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                    ],
                ]],
            ], 200),
        ]);

        $account = StorageAccount::factory()->googlePhotos()->create();
        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-upload-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'image-bytes');

        try {
            $result = app(StorageUploadService::class)->uploadStream(
                $account->id,
                $tempFile,
                'Flickr/photo.jpg',
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertNotEmpty($result['id']);
        $this->assertDatabaseHas('storage_remote_items', [
            'storage_account_id' => $account->id,
            'remote_id' => $result['id'],
        ]);
    }

    public function test_upload_stream_writes_r2_object_and_returns_metadata(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $objectKey = $account->credentials['prefix'].'/remote/photo.jpg';

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')
            ->once()
            ->with($objectKey, Mockery::type('resource'));

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->once()->andReturn([
            'ETag' => '"etag-upload"',
            'ContentLength' => 11,
        ]);

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk, $client): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-r2-upload-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'image-bytes');

        try {
            $result = app(StorageUploadService::class)->uploadStream(
                $account->id,
                $tempFile,
                'remote/photo.jpg',
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertSame('remote/photo.jpg', $result['path']);
        $this->assertSame('etag-upload', $result['etag']);
    }

    public function test_upload_stream_returns_null_etag_when_head_object_fails(): void
    {
        $account = StorageAccount::factory()->r2()->create();
        $objectKey = $account->credentials['prefix'].'/remote/photo.jpg';

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('writeStream')->once();

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->once()->andThrow(new \RuntimeException('missing object'));

        $this->mock(StorageFlysystemFactory::class, function ($mock) use ($disk, $client): void {
            $mock->shouldReceive('diskForAccount')->once()->andReturn($disk);
            $mock->shouldReceive('r2Client')->once()->andReturn($client);
        });

        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-r2-upload-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'image-bytes');

        try {
            $result = app(StorageUploadService::class)->uploadStream(
                $account->id,
                $tempFile,
                'remote/photo.jpg',
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertSame('remote/photo.jpg', $result['path']);
        $this->assertNull($result['etag']);
    }
}
