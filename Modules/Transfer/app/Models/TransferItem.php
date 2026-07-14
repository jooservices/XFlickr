<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Transfer\Database\Factories\TransferItemFactory;

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
        'flickr_photo_id',
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
}
