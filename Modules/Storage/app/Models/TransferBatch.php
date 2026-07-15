<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Storage\Database\Factories\TransferBatchFactory;
use Modules\Storage\Enums\TransferBatchStatus;

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
        'delete_local_after_upload',
    ];

    protected function casts(): array
    {
        return [
            'delete_local_after_upload' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StorageAccount, $this>
     */
    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }

    /**
     * @return HasMany<TransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_batch_id');
    }

    /**
     * @param  Builder<TransferBatch>  $query
     * @return Builder<TransferBatch>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }

    /**
     * @param  Builder<TransferBatch>  $query
     * @return Builder<TransferBatch>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<TransferBatch>  $query
     * @return Builder<TransferBatch>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', TransferBatchStatus::Running->value);
    }

    /**
     * @param  Builder<TransferBatch>  $query
     * @return Builder<TransferBatch>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TransferBatchStatus::Completed->value);
    }

    /**
     * @param  Builder<TransferBatch>  $query
     * @return Builder<TransferBatch>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', TransferBatchStatus::Failed->value);
    }
}
