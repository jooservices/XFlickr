<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Database\Factories\StorageUploadFactory;

/**
 * Remote storage upload row linking a Transfer file to its Storage account.
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

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    /**
     * @return BelongsTo<StorageAccount, $this>
     */
    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }

    /**
     * @param  Builder<StorageUpload>  $query
     * @return Builder<StorageUpload>
     */
    public function scopeForAccount(Builder $query, int $storageAccountId): Builder
    {
        return $query->where('storage_account_id', $storageAccountId);
    }
}
