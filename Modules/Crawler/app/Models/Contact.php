<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Crawler\Support\XFlickrConfig;

final class Contact extends Model
{
    protected $fillable = [
        'nsid',
        'username',
        'realname',
        'friend',
        'family',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'friend' => 'boolean',
            'family' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('contacts');
    }
}
