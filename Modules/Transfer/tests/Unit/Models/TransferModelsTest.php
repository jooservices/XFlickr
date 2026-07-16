<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Models;

use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Enums\TransferBatchStatus;
use Modules\Transfer\Enums\TransferItemStatus;
use Modules\Transfer\Models\StorageUpload;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Models\TransferBatch;
use Modules\Transfer\Models\TransferItem;
use Modules\Transfer\Tests\TestCase;

final class TransferModelsTest extends TestCase
{
    public function test_stored_file_boot_scopes_and_upload_relationship(): void
    {
        $file = StoredFile::query()->create([
            'source_type' => 'flickr_photo',
            'source_id' => 'photo-model',
            'source_owner' => 'owner@N01',
            'variant' => 'original',
            'original_name' => 'photo-model.jpg',
            'status' => StoredFileStatus::Completed->value,
        ]);
        $account = StorageAccount::factory()->googleDrive()->create();
        $upload = StorageUpload::factory()->create([
            'stored_file_id' => $file->id,
            'storage_account_id' => $account->id,
        ]);

        $this->assertNotEmpty($file->uuid);
        $this->assertSame('flickr_photo:photo-model:original', $file->dedup_key);
        $this->assertSame(1, StoredFile::query()->withStatus(StoredFileStatus::Completed)->count());
        $this->assertSame(0, StoredFile::query()->pending()->count());
        $this->assertSame(1, StoredFile::query()->completed()->count());
        $this->assertSame(0, StoredFile::query()->failed()->count());
        $this->assertTrue($upload->is($file->uploads()->first()));
        $this->assertTrue($file->is($upload->storedFile));
        $this->assertTrue($account->is($upload->storageAccount));
        $this->assertSame(1, StorageUpload::query()->forAccount($account->id)->count());
    }

    public function test_batch_and_item_scopes_and_relationships(): void
    {
        $account = StorageAccount::factory()->googleDrive()->create();
        $batch = TransferBatch::factory()->create([
            'connection_key' => 'connection-a',
            'storage_account_id' => $account->id,
            'status' => TransferBatchStatus::Running->value,
        ]);
        $item = TransferItem::factory()->create([
            'transfer_batch_id' => $batch->id,
            'status' => TransferItemStatus::Failed->value,
        ]);

        $this->assertTrue($account->is($batch->storageAccount));
        $this->assertTrue($item->is($batch->items()->first()));
        $this->assertTrue($batch->is($item->batch));
        $this->assertSame(1, TransferBatch::query()->forConnection('connection-a')->count());
        $this->assertSame(1, TransferBatch::query()->withStatus(TransferBatchStatus::Running)->count());
        $this->assertSame(1, TransferBatch::query()->running()->count());
        $this->assertSame(0, TransferBatch::query()->completed()->count());
        $this->assertSame(0, TransferBatch::query()->failed()->count());
        $this->assertSame(1, TransferItem::query()->withStatus(TransferItemStatus::Failed)->count());
        $this->assertSame(0, TransferItem::query()->pending()->count());
        $this->assertSame(0, TransferItem::query()->completed()->count());
        $this->assertSame(1, TransferItem::query()->failed()->count());
    }
}
