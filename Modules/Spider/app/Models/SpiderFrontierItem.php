<?php

declare(strict_types=1);

namespace Modules\Spider\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Spider\Database\Factories\SpiderFrontierItemFactory;
use Modules\Spider\Enums\SpiderFrontierStatus;

class SpiderFrontierItem extends Model
{
    /** @use HasFactory<SpiderFrontierItemFactory> */
    use HasFactory;

    protected static function newFactory(): SpiderFrontierItemFactory
    {
        return SpiderFrontierItemFactory::new();
    }

    protected $fillable = [
        'spider_run_id',
        'contact_nsid',
        'depth',
        'status',
        'crawled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SpiderFrontierStatus::class,
            'depth' => 'integer',
            'crawled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SpiderRun, $this>
     */
    public function spiderRun(): BelongsTo
    {
        return $this->belongsTo(SpiderRun::class);
    }

    /**
     * @param  Builder<SpiderFrontierItem>  $query
     * @return Builder<SpiderFrontierItem>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<SpiderFrontierItem>  $query
     * @return Builder<SpiderFrontierItem>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Pending);
    }

    /**
     * @param  Builder<SpiderFrontierItem>  $query
     * @return Builder<SpiderFrontierItem>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Queued);
    }

    /**
     * @param  Builder<SpiderFrontierItem>  $query
     * @return Builder<SpiderFrontierItem>
     */
    public function scopeCrawled(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Crawled);
    }
}
