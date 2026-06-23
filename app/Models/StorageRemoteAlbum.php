<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StorageRemoteAlbum extends Model
{
    protected $fillable = [
        'storage_account_id',
        'parent_remote_id',
        'remote_id',
        'title',
        'cover_thumbnail_url',
        'media_items_count',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'media_items_count' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toBrowseArray(): array
    {
        return [
            'id' => $this->remote_id,
            'title' => $this->title,
            'cover_thumbnail_url' => $this->cover_thumbnail_url,
            'media_items_count' => $this->media_items_count,
        ];
    }
}
