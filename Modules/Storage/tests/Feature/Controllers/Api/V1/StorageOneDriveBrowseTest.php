<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageOneDriveBrowseTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_onedrive_browse_lists_provider_folders_and_files(): void
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
                            'name' => 'XFlickr folder',
                            'folder' => ['childCount' => 1],
                        ]],
                    ]);
                }

                return Http::response([
                    'value' => [[
                        'id' => $fileId,
                        'name' => 'photo.jpg',
                        'file' => ['mimeType' => 'image/jpeg'],
                    ]],
                ]);
            },
        ]);

        $response = $this->getJson("/api/v1/storage/onedrive/files?account_id={$account->id}&source=provider");

        $response->assertOk();
        $response->assertJsonPath('data.albums.0.title', 'XFlickr folder');
        $response->assertJsonPath('data.items.0.name', 'photo.jpg');
        $response->assertJsonPath('meta.per_page', 25);
    }

    public function test_onedrive_browse_surfaces_graph_errors(): void
    {
        $account = StorageAccount::factory()->oneDrive()->create();

        Http::fake([
            'graph.microsoft.com/v1.0/me/drive/root/children*' => Http::response([
                'error' => ['message' => 'Token invalid'],
            ], 401),
        ]);

        $response = $this->getJson("/api/v1/storage/onedrive/files?account_id={$account->id}&source=provider");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Unable to browse storage.');
    }
}
