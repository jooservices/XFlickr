<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Storage\Database\Factories\StorageRemoteItemFactory;

final class StorageRemoteItem extends Model
{
    /** @use HasFactory<StorageRemoteItemFactory> */
    use HasFactory;

    protected static function newFactory(): StorageRemoteItemFactory
    {
        return StorageRemoteItemFactory::new();
    }

    protected $fillable = [
        'storage_account_id',
        'parent_remote_id',
        'remote_id',
        'name',
        'mime_type',
        'thumbnail_url',
        'size',
        'modified_at',
        'web_url',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'modified_at' => 'datetime',
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
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'thumbnail_url' => $this->thumbnail_url,
            'size' => $this->size,
            'modified_at' => $this->modified_at?->toIso8601String(),
            'path' => null,
            'web_url' => $this->web_url,
        ];
    }
}
