<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Storage\Database\Factories\StorageRemoteSyncStateFactory;

/**
 * @property int $id
 * @property int $storage_account_id
 * @property string|null $parent_remote_id
 * @property string|null $album_page_token
 * @property string|null $item_page_token
 * @property bool $albums_complete
 * @property bool $items_complete
 * @property bool $reconciling
 * @property array<string, mixed>|null $reconcile_snapshot
 * @property list<string>|null $reconcile_seen_remote_ids
 * @property Carbon|null $last_synced_at
 */
final class StorageRemoteSyncState extends Model
{
    /** @use HasFactory<StorageRemoteSyncStateFactory> */
    use HasFactory;

    protected static function newFactory(): StorageRemoteSyncStateFactory
    {
        return StorageRemoteSyncStateFactory::new();
    }

    protected $fillable = [
        'storage_account_id',
        'parent_remote_id',
        'album_page_token',
        'item_page_token',
        'albums_complete',
        'items_complete',
        'reconciling',
        'reconcile_snapshot',
        'reconcile_seen_remote_ids',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'albums_complete' => 'boolean',
            'items_complete' => 'boolean',
            'reconciling' => 'boolean',
            'reconcile_snapshot' => 'array',
            'reconcile_seen_remote_ids' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StorageAccount, $this>
     */
    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }
}
