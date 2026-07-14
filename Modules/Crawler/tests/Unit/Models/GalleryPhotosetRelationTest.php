<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Models;

use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class GalleryPhotosetRelationTest extends TestCase
{
    public function test_gallery_photos_relation_attaches_pivot(): void
    {
        $owner = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $owner,
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);
        $gallery = Gallery::query()->forceCreate([
            'flickr_gallery_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $owner,
            'title' => fake()->sentence(2),
            'description' => null,
            'photo_count' => 1,
            'raw_payload' => [],
        ]);

        $discoveredAt = now()->subMinute();
        $gallery->photos()->attach($photo->id, ['discovered_at' => $discoveredAt]);

        $related = $gallery->photos()->first();
        $this->assertNotNull($related);
        $this->assertSame($photo->id, $related->id);
        $this->assertNotNull($related->pivot->discovered_at);
    }

    public function test_photoset_photos_relation_attaches_pivot(): void
    {
        $owner = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $owner,
            'title' => fake()->sentence(3),
            'raw_payload' => [],
        ]);
        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $owner,
            'title' => fake()->sentence(2),
            'description' => null,
            'photo_count' => 1,
            'raw_payload' => [],
        ]);

        $discoveredAt = now()->subMinute();
        $photoset->photos()->attach($photo->id, ['discovered_at' => $discoveredAt]);

        $related = $photoset->photos()->first();
        $this->assertNotNull($related);
        $this->assertSame($photo->id, $related->id);
        $this->assertNotNull($related->pivot->discovered_at);
    }
}
