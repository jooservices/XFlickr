<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Models\Gallery;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Models\Photoset;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class PhotoQueryRepositoryTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_photoset_and_gallery_memberships_groups_photo_ids(): void
    {
        $photo = Photo::query()->create([
            'flickr_photo_id' => 'p-1',
            'owner_nsid' => 'owner@N01',
            'title' => 'Grouped photo',
        ]);

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => 'set-1',
            'owner_nsid' => 'owner@N01',
            'title' => 'Travel',
            'photo_count' => 1,
        ]);

        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => 'gal-1',
            'owner_nsid' => 'owner@N01',
            'title' => 'Favorites',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $photo->id,
        ]);

        DB::table(XFlickrConfig::table('gallery_photo'))->insert([
            'xflickr_gallery_id' => $gallery->id,
            'xflickr_photo_id' => $photo->id,
        ]);

        $memberships = app(PhotoQueryRepository::class)->photosetAndGalleryMemberships([$photo->id]);

        $this->assertCount(1, $memberships['photoset_rows']);
        $this->assertCount(1, $memberships['gallery_rows']);
        $this->assertSame('set-1', $memberships['photoset_rows']->first()->flickr_photoset_id);
        $this->assertSame('gal-1', $memberships['gallery_rows']->first()->flickr_gallery_id);
    }
}
