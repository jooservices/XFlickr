<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Models;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageRemoteAlbumTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_to_browse_array_maps_remote_fields(): void
    {
        $remoteId = fake()->uuid();
        $title = fake()->words(3, true);
        $coverUrl = 'https://lh3.googleusercontent.com/'.fake()->sha1();
        $count = fake()->numberBetween(1, 100);

        $album = StorageRemoteAlbum::factory()->create([
            'remote_id' => $remoteId,
            'title' => $title,
            'cover_thumbnail_url' => $coverUrl,
            'media_items_count' => $count,
        ]);

        $this->assertSame([
            'id' => $remoteId,
            'title' => $title,
            'cover_thumbnail_url' => $coverUrl,
            'media_items_count' => $count,
        ], $album->toBrowseArray());
    }

    public function test_storage_account_relationship(): void
    {
        $account = StorageAccount::factory()->googlePhotos()->create();
        $album = StorageRemoteAlbum::factory()->create([
            'storage_account_id' => $account->id,
        ]);

        $this->assertTrue($album->storageAccount->is($account));
    }
}
