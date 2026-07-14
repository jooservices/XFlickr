<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Storage\Database\Factories\StorageRemoteAlbumFactory;

final class StorageRemoteAlbum extends Model
{
    /** @use HasFactory<StorageRemoteAlbumFactory> */
    use HasFactory;

    protected static function newFactory(): StorageRemoteAlbumFactory
    {
        return StorageRemoteAlbumFactory::new();
    }

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

    /**
     * @return BelongsTo<StorageAccount, $this>
     */
    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }

    /**
     * @param  Builder<StorageRemoteAlbum>  $query
     * @return Builder<StorageRemoteAlbum>
     */
    public function scopeForAccount(Builder $query, int $storageAccountId): Builder
    {
        return $query->where('storage_account_id', $storageAccountId);
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
