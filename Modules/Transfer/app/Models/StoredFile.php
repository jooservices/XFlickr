<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Storage\Models\StorageUpload;

final class StoredFile extends Model
{
    protected $fillable = [
        'uuid',
        'flickr_photo_id',
        'owner_nsid',
        'variant',
        'local_path',
        'original_name',
        'mime_type',
        'bytes',
        'status',
        'dedup_key',
        'content_sha256',
        'metadata',
        'error_message',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'downloaded_at' => 'datetime',
            'bytes' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->dedup_key)) {
                $model->dedup_key = "flickr:{$model->flickr_photo_id}:{$model->variant}";
            }
        });
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(StorageUpload::class, 'stored_file_id');
    }
}
