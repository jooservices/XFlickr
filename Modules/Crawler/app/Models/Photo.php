<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Crawler\Support\XFlickrConfig;

final class Photo extends Model
{
    protected $fillable = [
        'flickr_photo_id',
        'owner_nsid',
        'title',
        'secret',
        'server',
        'farm',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'farm' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('photos');
    }
}
