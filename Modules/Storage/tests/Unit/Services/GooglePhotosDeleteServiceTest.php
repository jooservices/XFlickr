<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotosDeleteService;
use Modules\Storage\Services\StorageGoogleTokenService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class GooglePhotosDeleteServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_delete_many_requires_album_id(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Google Photos delete requires an album');

        app(GooglePhotosDeleteService::class)->deleteMany(
            $account,
            $account->credentials ?? [],
            ['media-1'],
            null,
        );
    }

    public function test_delete_many_returns_deleted_ids_on_success(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaId = 'media-'.fake()->uuid();

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums/*:batchRemoveMediaItems' => Http::response([], 200),
        ]);

        $result = app(GooglePhotosDeleteService::class)->deleteMany(
            $account,
            $account->credentials ?? [],
            [$mediaId],
            'album-42',
        );

        $this->assertSame([$mediaId], $result['deleted']);
        $this->assertSame([], $result['failed']);
    }

    public function test_delete_many_returns_failed_items_with_api_error_message(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $firstId = 'media-'.fake()->uuid();
        $secondId = 'media-'.fake()->uuid();

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums/*:batchRemoveMediaItems' => Http::response([
                'error' => ['message' => 'Album not writable'],
            ], 403),
        ]);

        $result = app(GooglePhotosDeleteService::class)->deleteMany(
            $account,
            $account->credentials ?? [],
            [$firstId, $secondId],
            'album-42',
        );

        $this->assertSame([], $result['deleted']);
        $this->assertCount(2, $result['failed']);
        $this->assertSame('Album not writable', $result['failed'][0]['message']);
        $this->assertSame($firstId, $result['failed'][0]['id']);
    }

    public function test_delete_many_uses_fallback_message_when_error_payload_is_empty(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $mediaId = 'media-'.fake()->uuid();

        $this->mock(StorageGoogleTokenService::class, function ($mock): void {
            $mock->shouldReceive('accessToken')->andReturn('access-token');
        });

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums/*:batchRemoveMediaItems' => Http::response('denied', 500),
        ]);

        $result = app(GooglePhotosDeleteService::class)->deleteMany(
            $account,
            $account->credentials ?? [],
            [$mediaId],
            'album-42',
        );

        $this->assertSame('Google Photos remove from album failed.', $result['failed'][0]['message']);
    }
}
