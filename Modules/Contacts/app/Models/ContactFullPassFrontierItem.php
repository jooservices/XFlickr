<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Spider\Enums\SpiderFrontierStatus;

class ContactFullPassFrontierItem extends Model
{
    protected $fillable = [
        'contact_full_pass_run_id',
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
     * @return BelongsTo<ContactFullPassRun, $this>
     */
    public function fullPassRun(): BelongsTo
    {
        return $this->belongsTo(ContactFullPassRun::class, 'contact_full_pass_run_id');
    }
}
