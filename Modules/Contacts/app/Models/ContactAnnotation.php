<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Contacts\Database\Factories\ContactAnnotationFactory;

/**
 * @property string $connection_key
 * @property string $contact_nsid
 * @property string|null $note
 * @property Carbon|null $starred_at
 */
final class ContactAnnotation extends Model
{
    /** @use HasFactory<ContactAnnotationFactory> */
    use HasFactory;

    protected static function newFactory(): ContactAnnotationFactory
    {
        return ContactAnnotationFactory::new();
    }

    protected $fillable = [
        'connection_key',
        'contact_nsid',
        'note',
        'starred_at',
    ];

    protected function casts(): array
    {
        return [
            'starred_at' => 'datetime',
        ];
    }

    public function isStarred(): bool
    {
        return $this->starred_at !== null;
    }

    /**
     * @param  Builder<ContactAnnotation>  $query
     * @return Builder<ContactAnnotation>
     */
    public function scopeForConnection(Builder $query, string $key): Builder
    {
        return $query->where('connection_key', $key);
    }
}
