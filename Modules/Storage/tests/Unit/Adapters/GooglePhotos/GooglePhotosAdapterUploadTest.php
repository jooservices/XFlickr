<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Adapters\GooglePhotos;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Storage\Dto\StorageUploadRequest;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageAdapterFactory;
use Modules\Storage\Tests\TestCase;

final class GooglePhotosAdapterUploadTest extends TestCase
{
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

        $account = StorageAccount::factory()->googlePhotos()->create();

        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-gp-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'fake-image-bytes');

        try {
            $result = app(StorageAdapterFactory::class)->make($account)->upload(
                new StorageUploadRequest($tempFile, 'Flickr/nsid/Photos/123_original.jpg'),
            );
        } finally {
            @unlink($tempFile);
        }

        $this->assertSame('media-item-123', $result->id);
        $this->assertSame('media-item-123', $result->path);

        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/uploads');
        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate');

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Third-party API call succeeded.'
                && ($context['url'] ?? null) === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate'
                && ($context['upload_token_present'] ?? null) === true
                && ! array_key_exists('upload_token', $context));
    }

    public function test_upload_creates_album_and_assigns_media_item_to_it(): void
    {
        $albumPage = 0;

        Http::fake(function (Request $request) use (&$albumPage) {
            if ($request->url() === 'https://photoslibrary.googleapis.com/v1/uploads') {
                return Http::response('upload-token-123', 200);
            }

            if (str_starts_with($request->url(), 'https://photoslibrary.googleapis.com/v1/albums')) {
                if ($request->method() === 'POST') {
                    return Http::response(['id' => 'album-123', 'title' => 'Summer'], 200);
                }

                return Http::response($albumPage++ === 0
                    ? ['albums' => [], 'nextPageToken' => 'next-page']
                    : ['albums' => []], 200);
            }

            if ($request->url() === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate') {
                return Http::response([
                    'newMediaItemResults' => [[
                        'mediaItem' => ['id' => 'media-item-123', 'filename' => 'photo.jpg'],
                    ]],
                ], 200);
            }

            return Http::response([], 404);
        });
        $account = StorageAccount::factory()->googlePhotos()->create();
        $tempFile = tempnam(sys_get_temp_dir(), 'xflickr-gp-');
        $this->assertNotFalse($tempFile);
        file_put_contents($tempFile, 'fake-image-bytes');

        try {
            app(StorageAdapterFactory::class)->make($account)->upload(
                new StorageUploadRequest($tempFile, 'Flickr/nsid/Photos/123_original.jpg', 'Summer'),
            );
        } finally {
            @unlink($tempFile);
        }

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'https://photoslibrary.googleapis.com/v1/albums?pageSize=50'));
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'pageToken=next-page'));
        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/albums'
            && $request->method() === 'POST'
            && $request['album']['title'] === 'Summer');
        Http::assertSent(fn ($request) => $request->url() === 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate'
            && $request['albumId'] === 'album-123');
    }
}
