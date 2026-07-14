<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crawler\Enums\CrawlStatus;
use Modules\Crawler\Enums\TaskType;
use Modules\Crawler\Support\XFlickrConfig;

/**
 * @property int $id
 * @property int $xflickr_crawl_run_id
 * @property TaskType $task_type
 * @property string|null $subject_nsid
 * @property string|null $subject_id
 * @property int $page
 * @property CrawlStatus $status
 * @property int $priority
 * @property int $retry_count
 * @property CrawlRun|null $crawlRun
 */
final class CrawlTarget extends Model
{
    protected $fillable = [
        'xflickr_crawl_run_id',
        'task_type',
        'subject_nsid',
        'subject_id',
        'page',
        'status',
        'priority',
        'locked_until',
        'last_crawled_at',
        'next_run_at',
        'last_result_count',
        'retry_count',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'status' => CrawlStatus::class,
            'page' => 'integer',
            'priority' => 'integer',
            'locked_until' => 'datetime',
            'last_crawled_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_result_count' => 'integer',
            'retry_count' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('crawl_targets');
    }

    /**
     * @return BelongsTo<CrawlRun, $this>
     */
    public function crawlRun(): BelongsTo
    {
        return $this->belongsTo(CrawlRun::class, 'xflickr_crawl_run_id');
    }
}
