<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Favorite extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'connection_key',
        'subject_nsid',
        'xflickr_photo_id',
        'photo_owner_nsid',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('xflickr-crawler.tables.favorites', 'xflickr_favorites');
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'xflickr_photo_id');
    }

    /**
     * @param  Builder<Favorite>  $query
     * @return Builder<Favorite>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }
}
