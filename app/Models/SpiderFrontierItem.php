<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SpiderFrontierStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpiderFrontierItem extends Model
{
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
}
