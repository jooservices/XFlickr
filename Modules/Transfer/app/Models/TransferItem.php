<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Transfer\Database\Factories\TransferItemFactory;
use Modules\Transfer\Enums\TransferItemStatus;

final class TransferItem extends Model
{
    /** @use HasFactory<TransferItemFactory> */
    use HasFactory;

    protected static function newFactory(): TransferItemFactory
    {
        return TransferItemFactory::new();
    }

    protected $fillable = [
        'transfer_batch_id',
        'source_id',
        'status',
        'error_message',
    ];

    /**
     * @return BelongsTo<TransferBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TransferBatch::class, 'transfer_batch_id');
    }

    /**
     * @param  Builder<TransferItem>  $query
     * @return Builder<TransferItem>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<TransferItem>  $query
     * @return Builder<TransferItem>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransferItemStatus::Pending->value);
    }

    /**
     * @param  Builder<TransferItem>  $query
     * @return Builder<TransferItem>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TransferItemStatus::Completed->value);
    }

    /**
     * @param  Builder<TransferItem>  $query
     * @return Builder<TransferItem>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', TransferItemStatus::Failed->value);
    }
}
