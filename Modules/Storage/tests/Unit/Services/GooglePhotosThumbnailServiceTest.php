<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosThumbnailService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class GooglePhotosThumbnailServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_stream_returns_image_response_for_valid_media_item(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaItemId = fake()->uuid();
        $baseUrl = 'https://lh3.googleusercontent.com/'.fake()->sha1();

        Http::fake([
            "photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}" => Http::response([
                'baseUrl' => $baseUrl,
            ], 200),
            $baseUrl.'=w128-h128' => Http::response('jpeg-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = app(GooglePhotosThumbnailService::class)->stream($account->id, $mediaItemId);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_stream_rejects_unknown_account(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos account not found.');

        app(GooglePhotosThumbnailService::class)->stream(999999, fake()->uuid());
    }

    public function test_stream_rejects_unauthorized_thumbnail_domain(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaItemId = fake()->uuid();

        Http::fake([
            "photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}" => Http::response([
                'baseUrl' => 'https://evil.example/thumb',
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or unauthorized thumbnail domain prefix.');

        app(GooglePhotosThumbnailService::class)->stream($account->id, $mediaItemId);
    }

    public function test_stream_rejects_non_google_photos_account(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos account not found.');

        app(GooglePhotosThumbnailService::class)->stream($account->id, fake()->uuid());
    }

    public function test_stream_rejects_metadata_http_failure(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaItemId = fake()->uuid();

        Http::fake([
            "photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}" => Http::response(['error' => 'not found'], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos media item could not be loaded.');

        app(GooglePhotosThumbnailService::class)->stream($account->id, $mediaItemId);
    }

    public function test_stream_rejects_missing_base_url(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaItemId = fake()->uuid();

        Http::fake([
            "photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}" => Http::response([], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos media item has no preview URL.');

        app(GooglePhotosThumbnailService::class)->stream($account->id, $mediaItemId);
    }

    public function test_stream_rejects_thumbnail_http_failure(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaItemId = fake()->uuid();
        $baseUrl = 'https://lh3.googleusercontent.com/'.fake()->sha1();

        Http::fake([
            "photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}" => Http::response([
                'baseUrl' => $baseUrl,
            ], 200),
            $baseUrl.'=w128-h128' => Http::response('denied', 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Photos thumbnail could not be loaded.');

        app(GooglePhotosThumbnailService::class)->stream($account->id, $mediaItemId);
    }
}
