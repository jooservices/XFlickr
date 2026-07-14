<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Models;

use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class TransferModelScopeTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_transfer_batch_for_connection_and_running_scopes(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $running = TransferBatch::factory()->create([
            'connection_key' => $matchKey,
            'status' => TransferBatchStatus::Running->value,
        ]);
        TransferBatch::factory()->create([
            'connection_key' => $matchKey,
            'status' => TransferBatchStatus::Completed->value,
        ]);
        TransferBatch::factory()->create([
            'connection_key' => $otherKey,
            'status' => TransferBatchStatus::Running->value,
        ]);

        $this->assertTrue(
            TransferBatch::query()->forConnection($matchKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertFalse(
            TransferBatch::query()->forConnection($otherKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertSame(1, TransferBatch::query()->forConnection($matchKey)->completed()->count());
    }

    public function test_transfer_item_status_scopes(): void
    {
        $failed = TransferItem::factory()->create([
            'status' => TransferItemStatus::Failed->value,
        ]);
        TransferItem::factory()->create([
            'status' => TransferItemStatus::Pending->value,
        ]);

        $this->assertTrue(TransferItem::query()->failed()->whereKey($failed->id)->exists());
        $this->assertFalse(TransferItem::query()->pending()->whereKey($failed->id)->exists());
        $this->assertSame(1, TransferItem::query()->withStatus(TransferItemStatus::Pending)->count());
    }

    public function test_stored_file_status_scopes(): void
    {
        $completed = StoredFile::factory()->create([
            'status' => StoredFileStatus::Completed->value,
            'variant' => 'original',
        ]);
        StoredFile::factory()->create([
            'status' => StoredFileStatus::Pending->value,
            'variant' => 'original',
        ]);

        $this->assertTrue(StoredFile::query()->completed()->whereKey($completed->id)->exists());
        $this->assertFalse(StoredFile::query()->pending()->whereKey($completed->id)->exists());
        $this->assertSame(1, StoredFile::query()->withStatus(StoredFileStatus::Pending)->count());
    }
}
