<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StorageUpload extends Model
{
    protected $fillable = [
        'stored_file_id',
        'storage_account_id',
        'remote_file_id',
        'remote_path',
        'remote_etag',
        'status',
        'error_message',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function storageAccount(): BelongsTo
    {
        return $this->belongsTo(StorageAccount::class, 'storage_account_id');
    }
}
