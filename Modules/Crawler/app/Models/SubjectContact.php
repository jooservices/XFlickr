<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SubjectContact extends Model
{
    protected $fillable = [
        'connection_key',
        'subject_nsid',
        'contact_nsid',
        'crawl_run_id',
        'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'crawl_run_id' => 'integer',
            'discovered_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return (string) config('xflickr-crawler.tables.subject_contacts', 'xflickr_subject_contacts');
    }

    /**
     * @param  Builder<SubjectContact>  $query
     * @return Builder<SubjectContact>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }
}
