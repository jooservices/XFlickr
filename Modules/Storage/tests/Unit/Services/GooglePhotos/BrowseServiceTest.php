<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services\GooglePhotos;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\GooglePhotos\BrowseService;
use Modules\Storage\Tests\TestCase;

final class BrowseServiceTest extends TestCase
{
    public function test_browse_lists_albums_and_media_items(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response(
                $this->loadJsonFixture('google-photos-albums.json'),
                200,
            ),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [[
                    'id' => 'media-1',
                    'filename' => 'photo.jpg',
                    'mimeType' => 'image/jpeg',
                    'baseUrl' => 'https://example.com/photo',
                    'mediaMetadata' => ['creationTime' => '2026-01-01T00:00:00Z'],
                ]],
                'nextPageToken' => 'item-next',
            ], 200),
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 10,
            albumPageToken: 'album-page-1',
            itemPageToken: 'item-page-1',
            containerId: null,
            includeAlbums: true,
            includeItems: true,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame('album-next', $result->albumNextPageToken);
        $this->assertCount(1, $result->items);
        $this->assertSame('item-next', $result->itemNextPageToken);
    }

    public function test_browse_can_scope_items_to_album_and_skip_album_listing(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [],
            ], 200),
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: 'album-42',
            includeAlbums: false,
            includeItems: true,
        );

        $this->assertSame([], $result->albums);
        $this->assertNull($result->albumNextPageToken);
        $this->assertSame([], $result->items);
    }

    public function test_browse_uses_thumbnail_route_when_cover_media_id_is_present(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();

        Http::fake([
            'photoslibrary.googleapis.com/v1/albums*' => Http::response([
                'albums' => [[
                    'id' => 'album-1',
                    'title' => 'Cover album',
                    'coverPhotoMediaItemId' => 'cover-media-1',
                ]],
            ], 200),
            'photoslibrary.googleapis.com/v1/mediaItems:search' => Http::response([
                'mediaItems' => [],
            ], 200),
        ]);

        $result = app(BrowseService::class)->browse(
            $account,
            perPage: 25,
            albumPageToken: null,
            itemPageToken: null,
            containerId: null,
            includeAlbums: true,
            includeItems: false,
        );

        $this->assertStringContainsString(
            '/api/v1/storage/google-photos/thumbnail',
            (string) $result->albums[0]['cover_thumbnail_url'],
        );
    }
}
