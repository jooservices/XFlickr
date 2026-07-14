<?php

declare(strict_types=1);

namespace Modules\Spider\Models;

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
}
