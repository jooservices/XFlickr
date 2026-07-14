<?php

declare(strict_types=1);

namespace Modules\Transfer\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Storage\Models\StorageUpload;
use Modules\Transfer\Database\Factories\StoredFileFactory;
use Modules\Transfer\Enums\StoredFileStatus;

final class StoredFile extends Model
{
    /** @use HasFactory<StoredFileFactory> */
    use HasFactory;

    protected static function newFactory(): StoredFileFactory
    {
        return StoredFileFactory::new();
    }

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

    /**
     * @return HasMany<StorageUpload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(StorageUpload::class, 'stored_file_id');
    }

    /**
     * @param  Builder<StoredFile>  $query
     * @return Builder<StoredFile>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<StoredFile>  $query
     * @return Builder<StoredFile>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StoredFileStatus::Pending->value);
    }

    /**
     * @param  Builder<StoredFile>  $query
     * @return Builder<StoredFile>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', StoredFileStatus::Completed->value);
    }

    /**
     * @param  Builder<StoredFile>  $query
     * @return Builder<StoredFile>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', StoredFileStatus::Failed->value);
    }
}
