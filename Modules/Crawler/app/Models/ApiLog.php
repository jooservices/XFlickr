<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Crawler\Enums\ApiOutcome;
use Modules\Crawler\Support\XFlickrConfig;

/**
 * @property int $id
 * @property string $connection_key
 * @property int|null $xflickr_crawl_run_id
 * @property int|null $xflickr_crawl_target_id
 * @property string $api_method
 * @property ApiOutcome $outcome
 * @property int|null $latency_ms
 * @property int|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
final class ApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'connection_key',
        'xflickr_crawl_run_id',
        'xflickr_crawl_target_id',
        'api_method',
        'outcome',
        'latency_ms',
        'error_code',
        'error_message',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => ApiOutcome::class,
            'latency_ms' => 'integer',
            'error_code' => 'integer',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('api_logs');
    }
}
