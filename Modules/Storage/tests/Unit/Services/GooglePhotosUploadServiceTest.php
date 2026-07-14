<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosUploadService;
use Modules\Storage\Services\StorageGoogleTokenService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class GooglePhotosUploadServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_upload_file_throws_when_upload_http_request_fails(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $localPath = $this->tempImage('upload-fail.jpg');

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('denied', 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos upload failed: HTTP 403');

        app(GooglePhotosUploadService::class)->uploadFile(
            $account,
            $account->credentials ?? [],
            $localPath,
            'remote/upload-fail.jpg',
        );
    }

    public function test_upload_file_throws_when_upload_token_is_empty(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $localPath = $this->tempImage('empty-token.jpg');

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('   ', 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos upload token was empty.');

        app(GooglePhotosUploadService::class)->uploadFile(
            $account,
            $account->credentials ?? [],
            $localPath,
            'remote/empty-token.jpg',
        );
    }

    public function test_upload_file_throws_when_batch_create_fails(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $localPath = $this->tempImage('batch-fail.jpg');

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'error' => ['message' => 'Album quota exceeded'],
            ], 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Album quota exceeded');

        app(GooglePhotosUploadService::class)->uploadFile(
            $account,
            $account->credentials ?? [],
            $localPath,
            'remote/batch-fail.jpg',
        );
    }

    public function test_upload_file_throws_when_media_item_id_is_missing(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $localPath = $this->tempImage('missing-media-id.jpg');

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'status' => ['message' => 'Media item was not created.'],
                ]],
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Media item was not created.');

        app(GooglePhotosUploadService::class)->uploadFile(
            $account,
            $account->credentials ?? [],
            $localPath,
            'remote/missing-media-id.jpg',
        );
    }

    private function tempImage(string $filename): string
    {
        $path = sys_get_temp_dir().'/'.$filename;
        file_put_contents($path, 'fake-image-bytes');

        return $path;
    }
}
