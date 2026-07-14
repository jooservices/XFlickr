<?php

declare(strict_types=1);

namespace Modules\Crawler\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Crawler\Support\XFlickrConfig;

final class Gallery extends Model
{
    protected $fillable = [
        'flickr_gallery_id',
        'owner_nsid',
        'title',
        'description',
        'photo_count',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'photo_count' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function getTable(): string
    {
        return XFlickrConfig::table('galleries');
    }

    /**
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(
            Photo::class,
            XFlickrConfig::table('gallery_photo'),
            'xflickr_gallery_id',
            'xflickr_photo_id',
        )->withPivot('discovered_at');
    }
}
