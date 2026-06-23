<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TransferItem extends Model
{
    protected $fillable = [
        'transfer_batch_id',
        'flickr_photo_id',
        'status',
        'error_message',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TransferBatch::class, 'transfer_batch_id');
    }
}
