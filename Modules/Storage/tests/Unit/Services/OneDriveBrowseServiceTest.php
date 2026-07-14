<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\OneDriveBrowseService;
use RuntimeException;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class OneDriveBrowseServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_browse_lists_folders_and_image_files(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();
        $folderId = fake()->uuid();
        $fileId = fake()->uuid();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive/root/children*' => function ($request) use ($folderId, $fileId) {
                $filter = $request->data()['$filter'] ?? '';

                if ($filter === 'folder ne null') {
                    return Http::response([
                        'value' => [[
                            'id' => $folderId,
                            'name' => fake()->words(2, true),
                            'folder' => ['childCount' => 3],
                            'thumbnails' => [['medium' => ['url' => 'https://example.com/thumb-folder']]],
                        ]],
                    ]);
                }

                return Http::response([
                    'value' => [
                        [
                            'id' => $fileId,
                            'name' => fake()->word().'.jpg',
                            'file' => ['mimeType' => 'image/jpeg'],
                            'size' => 2048,
                            'lastModifiedDateTime' => '2026-01-01T00:00:00Z',
                            'webUrl' => 'https://onedrive.example/file',
                            'thumbnails' => [['small' => ['url' => 'https://example.com/thumb-file']]],
                        ],
                        [
                            'id' => fake()->uuid(),
                            'name' => 'notes.txt',
                            'file' => ['mimeType' => 'text/plain'],
                        ],
                    ],
                ]);
            },
        ]);

        $result = app(OneDriveBrowseService::class)->browse(
            $account->credentials ?? [],
            $account,
            perPage: 25,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame($folderId, $result->albums[0]['id']);
        $this->assertCount(1, $result->items);
        $this->assertSame($fileId, $result->items[0]['id']);
        $this->assertSame('image/jpeg', $result->items[0]['mime_type']);
    }

    public function test_browse_follows_folder_children_when_container_id_is_set(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();
        $containerId = fake()->uuid();

        Http::fake([
            "graph.microsoft.com/v1.0/me/drive/items/{$containerId}/children*" => Http::response([
                'value' => [[
                    'id' => fake()->uuid(),
                    'name' => fake()->word().'.png',
                    'file' => ['mimeType' => 'image/png'],
                ]],
            ]),
        ]);

        $result = app(OneDriveBrowseService::class)->browse(
            $account->credentials ?? [],
            $account,
            folderId: $containerId,
            includeAlbums: false,
        );

        $this->assertSame([], $result->albums);
        $this->assertCount(1, $result->items);
    }

    public function test_browse_uses_pagination_tokens(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();
        $nextAlbumLink = 'https://graph.microsoft.com/v1.0/me/drive/root/children?$skiptoken=album-next';
        $nextItemLink = 'https://graph.microsoft.com/v1.0/me/drive/root/children?$skiptoken=item-next';

        Http::fake([
            $nextAlbumLink => Http::response([
                'value' => [[
                    'id' => fake()->uuid(),
                    'name' => 'Paged folder',
                    'folder' => ['childCount' => 1],
                ]],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/drive/root/children?$skiptoken=album-page-3',
            ]),
            $nextItemLink => Http::response([
                'value' => [[
                    'id' => fake()->uuid(),
                    'name' => 'paged.jpg',
                    'file' => ['mimeType' => 'image/jpeg'],
                ]],
            ]),
        ]);

        $result = app(OneDriveBrowseService::class)->browse(
            $account->credentials ?? [],
            $account,
            albumPageToken: $nextAlbumLink,
            itemPageToken: $nextItemLink,
        );

        $this->assertCount(1, $result->albums);
        $this->assertSame(
            'https://graph.microsoft.com/v1.0/me/drive/root/children?$skiptoken=album-page-3',
            $result->albumNextPageToken,
        );
        $this->assertCount(1, $result->items);
    }

    public function test_browse_throws_when_graph_returns_error(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive/root/children*' => Http::response([
                'error' => ['message' => 'Access denied'],
            ], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Access denied');

        app(OneDriveBrowseService::class)->browse($account->credentials ?? [], $account);
    }

    public function test_browse_can_skip_albums_or_items(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive/root/children*' => Http::response([
                'value' => [[
                    'id' => fake()->uuid(),
                    'name' => 'only-file.jpg',
                    'file' => ['mimeType' => 'image/jpeg'],
                ]],
            ]),
        ]);

        $result = app(OneDriveBrowseService::class)->browse(
            $account->credentials ?? [],
            $account,
            includeAlbums: false,
        );

        $this->assertSame([], $result->albums);
        $this->assertCount(1, $result->items);
    }
}
