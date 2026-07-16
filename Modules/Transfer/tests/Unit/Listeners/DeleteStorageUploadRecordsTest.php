<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Listeners;

use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Listeners\DeleteStorageUploadRecords;
use Modules\Transfer\Models\StorageUpload;
use Modules\Transfer\Models\StoredFile;
use Modules\Transfer\Tests\TestCase;

final class DeleteStorageUploadRecordsTest extends TestCase
{
    public function test_it_deletes_only_matching_upload_records_for_the_account(): void
    {
        $account = StorageAccount::factory()->create();
        $otherAccount = StorageAccount::factory()->create();
        $storedFile = StoredFile::factory()->create();

        $matching = StorageUpload::factory()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $account->id,
            'remote_file_id' => 'remote-a',
        ]);
        $otherAccountUpload = StorageUpload::factory()->create([
            'stored_file_id' => $storedFile->id,
            'storage_account_id' => $otherAccount->id,
            'remote_file_id' => 'remote-a',
        ]);

        app(DeleteStorageUploadRecords::class)->handle(
            new StorageRemoteItemsRemoved($account->id, ['remote-a']),
        );

        $this->assertModelMissing($matching);
        $this->assertModelExists($otherAccountUpload);
    }
}
