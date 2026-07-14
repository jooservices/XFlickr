<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Contacts\Database\Factories\ContactFullPassFrontierItemFactory;
use Modules\Spider\Enums\SpiderFrontierStatus;

class ContactFullPassFrontierItem extends Model
{
    /** @use HasFactory<ContactFullPassFrontierItemFactory> */
    use HasFactory;

    protected static function newFactory(): ContactFullPassFrontierItemFactory
    {
        return ContactFullPassFrontierItemFactory::new();
    }

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

    /**
     * @param  Builder<ContactFullPassFrontierItem>  $query
     * @return Builder<ContactFullPassFrontierItem>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<ContactFullPassFrontierItem>  $query
     * @return Builder<ContactFullPassFrontierItem>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Pending);
    }

    /**
     * @param  Builder<ContactFullPassFrontierItem>  $query
     * @return Builder<ContactFullPassFrontierItem>
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Queued);
    }

    /**
     * @param  Builder<ContactFullPassFrontierItem>  $query
     * @return Builder<ContactFullPassFrontierItem>
     */
    public function scopeCrawled(Builder $query): Builder
    {
        return $query->where('status', SpiderFrontierStatus::Crawled);
    }
}
