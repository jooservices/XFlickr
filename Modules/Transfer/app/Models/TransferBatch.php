<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Database\Factories\TransferBatchFactory;

final class TransferBatch extends Model
{
    /** @use HasFactory<TransferBatchFactory> */
    use HasFactory;

    protected static function newFactory(): TransferBatchFactory
    {
        return TransferBatchFactory::new();
    }

    protected $fillable = [
        'type',
        'connection_key',
        'subject_nsid',
        'group_type',
        'group_id',
        'group_label',
        'storage_account_id',
        'status',
        'total_count',
        'completed_count',
        'failed_count',
    ];

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_batch_id');
    }
}
