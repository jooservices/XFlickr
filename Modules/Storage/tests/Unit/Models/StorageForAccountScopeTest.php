<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Models;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageRemoteAlbum;
use Modules\Storage\Models\StorageRemoteItem;
use Modules\Storage\Models\StorageRemoteSyncState;
use Modules\Transfer\Models\StorageUpload;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class StorageForAccountScopeTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_for_account_scopes_match_and_exclude(): void
    {
        $match = StorageAccount::factory()->create();
        $other = StorageAccount::factory()->create();

        $upload = StorageUpload::factory()->create(['storage_account_id' => $match->id]);
        StorageUpload::factory()->create(['storage_account_id' => $other->id]);

        $item = StorageRemoteItem::factory()->create(['storage_account_id' => $match->id]);
        StorageRemoteItem::factory()->create(['storage_account_id' => $other->id]);

        $album = StorageRemoteAlbum::factory()->create(['storage_account_id' => $match->id]);
        StorageRemoteAlbum::factory()->create(['storage_account_id' => $other->id]);

        $state = StorageRemoteSyncState::factory()->create(['storage_account_id' => $match->id]);
        StorageRemoteSyncState::factory()->create(['storage_account_id' => $other->id]);

        $this->assertTrue(StorageUpload::query()->forAccount($match->id)->whereKey($upload->id)->exists());
        $this->assertFalse(StorageUpload::query()->forAccount($other->id)->whereKey($upload->id)->exists());

        $this->assertTrue(StorageRemoteItem::query()->forAccount($match->id)->whereKey($item->id)->exists());
        $this->assertFalse(StorageRemoteItem::query()->forAccount($other->id)->whereKey($item->id)->exists());

        $this->assertTrue(StorageRemoteAlbum::query()->forAccount($match->id)->whereKey($album->id)->exists());
        $this->assertFalse(StorageRemoteAlbum::query()->forAccount($other->id)->whereKey($album->id)->exists());

        $this->assertTrue(StorageRemoteSyncState::query()->forAccount($match->id)->whereKey($state->id)->exists());
        $this->assertFalse(StorageRemoteSyncState::query()->forAccount($other->id)->whereKey($state->id)->exists());
    }
}
