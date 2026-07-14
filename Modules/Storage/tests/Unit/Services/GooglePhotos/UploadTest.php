<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\GooglePhotos;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotos\UploadService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class UploadTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_uploads_media_item_via_google_photos_api(): void
    {
        Log::spy();

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
            $result = app(UploadService::class)->uploadFile(
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

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Third-party API call succeeded.'
                && ($context['url'] ?? null) === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate'
                && ($context['upload_token_present'] ?? null) === true
                && ! array_key_exists('upload_token', $context));
    }
}
