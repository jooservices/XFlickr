<?php

declare(strict_types=1);

namespace Modules\Transfer\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Transfer\Models\TransferBatch;

/** @extends Factory<TransferBatch> */
class TransferBatchFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = TransferBatch::class;

    public function definition(): array
    {
        return [
            'type' => 'download',
            'connection_key' => $this->flickrNsid(),
            'subject_nsid' => $this->flickrNsid(),
            'group_type' => null,
            'group_id' => null,
            'group_label' => null,
            'storage_account_id' => null,
            'status' => 'pending',
            'total_count' => 0,
            'completed_count' => 0,
            'failed_count' => 0,
            'delete_local_after_upload' => null,
        ];
    }
}
