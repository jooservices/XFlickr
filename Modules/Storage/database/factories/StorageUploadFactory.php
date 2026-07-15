<?php

declare(strict_types=1);

namespace Modules\Storage\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Models\StorageUpload;
use Modules\Storage\Models\StoredFile;

/** @extends Factory<StorageUpload> */
class StorageUploadFactory extends Factory
{
    protected $model = StorageUpload::class;

    public function definition(): array
    {
        return [
            'stored_file_id' => StoredFile::factory(),
            'storage_account_id' => StorageAccount::factory(),
            'remote_file_id' => fake()->uuid(),
            'remote_path' => '/'.fake()->lexify('????/????.jpg'),
            'remote_etag' => fake()->md5(),
            'status' => 'uploaded',
            'error_message' => null,
            'uploaded_at' => now(),
        ];
    }
}
