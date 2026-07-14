<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConnectionContact extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'connection_key',
        'contact_nsid',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('xflickr-crawler.tables.connection_contacts', 'xflickr_connection_contacts');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_nsid', 'nsid');
    }

    /**
     * @param  Builder<ConnectionContact>  $query
     * @return Builder<ConnectionContact>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }
}
