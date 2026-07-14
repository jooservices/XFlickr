<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Storage\Database\Factories\StorageUploadFactory;

/**
 * Remote storage upload row. Holds `stored_file_id` as an integer FK only (no Eloquent
 * relation into Transfer) so the module DAG stays one-way; use StoredFile::uploads().
 */
final class StorageUpload extends Model
{
    /** @use HasFactory<StorageUploadFactory> */
    use HasFactory;

    protected static function newFactory(): StorageUploadFactory
    {
        return StorageUploadFactory::new();
    }

    protected $fillable = [
        'stored_file_id',
        'storage_account_id',
        'remote_file_id',
        'remote_path',
        'remote_etag',
        'status',
        'error_message',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }
}
