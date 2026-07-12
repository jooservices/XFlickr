<?php

declare(strict_types=1);

namespace Modules\Contacts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $connection_key
 * @property string $contact_nsid
 * @property string|null $note
 * @property Carbon|null $starred_at
 */
final class ContactAnnotation extends Model
{
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
}
