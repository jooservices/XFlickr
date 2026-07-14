<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Spider\Enums\SpiderRunStatus;

/**
 * @property SpiderRunStatus $status
 */
class ContactFullPassRun extends Model
{
    /** @use HasFactory<ContactFullPassRunFactory> */
    use HasFactory;

    protected static function newFactory(): ContactFullPassRunFactory
    {
        return ContactFullPassRunFactory::new();
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
     * @return HasMany<ContactFullPassFrontierItem, $this>
     */
    public function frontierItems(): HasMany
    {
        return $this->hasMany(ContactFullPassFrontierItem::class);
    }

    /**
     * @param  Builder<ContactFullPassRun>  $query
     * @return Builder<ContactFullPassRun>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }

    /**
     * @param  Builder<ContactFullPassRun>  $query
     * @return Builder<ContactFullPassRun>
     */
    public function scopeWithStatus(Builder $query, BackedEnum|string $status): Builder
    {
        return $query->where('status', $status instanceof BackedEnum ? $status->value : $status);
    }

    /**
     * @param  Builder<ContactFullPassRun>  $query
     * @return Builder<ContactFullPassRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', SpiderRunStatus::Running);
    }
}
