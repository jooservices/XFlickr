<?php

declare(strict_types=1);

namespace Modules\Storage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StorageRemoteSyncState extends Model
{
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

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }
}
