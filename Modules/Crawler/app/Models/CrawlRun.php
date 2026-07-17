<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Support\XFlickrConfig;

/**
 * @property int $id
 * @property string $connection_key
 * @property string|null $crawl_type
 * @property string|null $subject_nsid
 * @property int|null $spider_run_id
 * @property int|null $spider_frontier_item_id
 * @property CrawlRunStatus $status
 * @property int $contacts_discovered
 * @property int $photos_discovered
 * @property int $targets_failed
 * @property int $api_calls
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $failed_reason
 */
final class CrawlRun extends Model
{
    protected $fillable = [
        'connection_key',
        'crawl_type',
        'subject_nsid',
        'status',
        'contacts_discovered',
        'photos_discovered',
        'targets_failed',
        'api_calls',
        'started_at',
        'completed_at',
        'failed_reason',
        'spider_run_id',
        'spider_frontier_item_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrawlRunStatus::class,
            'contacts_discovered' => 'integer',
            'photos_discovered' => 'integer',
            'targets_failed' => 'integer',
            'api_calls' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'spider_run_id' => 'integer',
            'spider_frontier_item_id' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('crawl_runs');
    }

    /**
     * @return HasMany<CrawlTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(CrawlTarget::class, 'xflickr_crawl_run_id');
    }

    /**
     * @param  Builder<CrawlRun>  $query
     * @return Builder<CrawlRun>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }

    /**
     * @param  Builder<CrawlRun>  $query
     * @return Builder<CrawlRun>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<CrawlRun>  $query
     * @return Builder<CrawlRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', CrawlRunStatus::Running);
    }

    /**
     * @param  Builder<CrawlRun>  $query
     * @return Builder<CrawlRun>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CrawlRunStatus::Completed);
    }

    /**
     * @param  Builder<CrawlRun>  $query
     * @return Builder<CrawlRun>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', CrawlRunStatus::Failed);
    }
}
