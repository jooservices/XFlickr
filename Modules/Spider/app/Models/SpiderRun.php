<?php

declare(strict_types=1);

namespace Modules\Spider\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Spider\Database\Factories\SpiderRunFactory;
use Modules\Spider\Enums\SpiderRunStatus;

/**
 * @property SpiderRunStatus $status
 */
class SpiderRun extends Model
{
    /** @use HasFactory<SpiderRunFactory> */
    use HasFactory;

    protected static function newFactory(): SpiderRunFactory
    {
        return SpiderRunFactory::new();
    }

    protected $fillable = [
        'connection_key',
        'status',
        'max_depth',
        'contacts_discovered',
        'contacts_crawled',
        'paused_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SpiderRunStatus::class,
            'max_depth' => 'integer',
            'contacts_discovered' => 'integer',
            'contacts_crawled' => 'integer',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SpiderFrontierItem, $this>
     */
    public function frontierItems(): HasMany
    {
        return $this->hasMany(SpiderFrontierItem::class);
    }

    /**
     * @param  Builder<SpiderRun>  $query
     * @return Builder<SpiderRun>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }

    /**
     * @param  Builder<SpiderRun>  $query
     * @return Builder<SpiderRun>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<SpiderRun>  $query
     * @return Builder<SpiderRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', SpiderRunStatus::Running);
    }
}
