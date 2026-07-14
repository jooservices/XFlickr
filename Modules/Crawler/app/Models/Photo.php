<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Crawler\Support\XFlickrConfig;

/**
 * @property int $id
 * @property string $flickr_photo_id
 * @property string $owner_nsid
 * @property string|null $title
 * @property string|null $secret
 * @property string|null $server
 * @property int|null $farm
 * @property array<string, mixed>|null $raw_payload
 */
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

    /**
     * @param  Builder<Photo>  $query
     * @return Builder<Photo>
     */
    public function scopeForOwner(Builder $query, string $nsid): Builder
    {
        return $query->where('owner_nsid', $nsid);
    }
}
