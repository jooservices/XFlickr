<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosUploadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class GooglePhotosUploadTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_uploads_media_item_via_google_photos_api(): void
    {
        Http::fake([
            'photoslibrary.googleapis.com/v1/uploads' => Http::response('upload-token-123', 200),
            'photoslibrary.googleapis.com/v1/mediaItems:batchCreate' => Http::response([
                'newMediaItemResults' => [[
                    'mediaItem' => [
                        'id' => 'media-item-123',
                        'filename' => 'photo.jpg',
                    ],
                ]],
            ], 200),
        ]);

        $account = StorageAccount::query()->create([
            'provider' => 'google_photos',
            'label' => 'Photos',
            'credentials' => [
                'access_token' => 'token',
                'refresh_token' => 'refresh',
                'client_id' => 'client',
                'client_secret' => 'secret',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'connected_at' => now(),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-gp-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'fake-image-bytes');

        try {
            $result = app(GooglePhotosUploadService::class)->uploadFile(
                $account,
                $account->credentials ?? [],
                $tempFile,
                'Flickr/nsid/Photos/123_original.jpg',
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertSame('media-item-123', $result['id']);
        $this->assertSame('media-item-123', $result['path']);

        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/uploads');
        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate');
    }
}
