<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Services;

use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Crawler\Services\FlickrFavoritesPersistence;
use Modules\Crawler\Tests\TestCase;

final class FlickrCatalogServiceTest extends TestCase
{
    private FlickrCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = app(FlickrCatalogService::class);
    }

    public function test_persist_contacts_skips_empty_nsid(): void
    {
        $count = $this->catalog->persistContacts([
            ['username' => 'ghost'],
            [
                'nsid' => '111@N01',
                'username' => 'alice',
                'realname' => 'Alice',
                'friend' => '1',
                'family' => '0',
            ],
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('xflickr_contacts', ['nsid' => '111@N01']);
    }

    public function test_persist_photo_page_with_owner_metadata(): void
    {
        $count = $this->catalog->persistPhotoPage([
            [
                'id' => 'photo-1',
                'owner' => '222@N01',
                'ownername' => 'bob',
                'title' => 'Sunset',
                'secret' => 'abc',
                'server' => '1',
                'farm' => 6,
            ],
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('xflickr_photos', ['flickr_photo_id' => 'photo-1']);
        $this->assertDatabaseHas('xflickr_contacts', ['nsid' => '222@N01', 'username' => 'bob']);
    }

    public function test_persist_photosets_and_galleries(): void
    {
        $photosetCount = $this->catalog->persistPhotosets([
            ['id' => 'ps-1', 'title' => 'Trip', 'photos' => 3],
            ['title' => 'missing id'],
        ], '333@N01');

        $galleryCount = $this->catalog->persistGalleries([
            ['id' => 'gal-1', 'title' => 'Favorites', 'count' => 2],
        ], '333@N01');

        $this->assertSame(1, $photosetCount);
        $this->assertSame(1, $galleryCount);
        $this->assertDatabaseHas('xflickr_photosets', ['flickr_photoset_id' => 'ps-1']);
        $this->assertDatabaseHas('xflickr_galleries', ['flickr_gallery_id' => 'gal-1']);
    }

    public function test_persist_photosets_unwraps_flickr_content_fields(): void
    {
        $count = $this->catalog->persistPhotosets([
            [
                'id' => 'ps-flickr',
                'title' => ['_content' => 'TÚ UYÊN'],
                'description' => ['_content' => ''],
                'photos' => 25,
            ],
        ], '333@N01');

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('xflickr_photosets', [
            'flickr_photoset_id' => 'ps-flickr',
            'title' => 'TÚ UYÊN',
            'description' => null,
        ]);
    }

    public function test_persist_photoset_photo_page_attaches_pivot(): void
    {
        Photoset::query()->create([
            'flickr_photoset_id' => 'ps-attach',
            'owner_nsid' => '444@N01',
            'title' => 'Set',
            'photo_count' => 0,
            'raw_payload' => '{}',
        ]);

        $count = $this->catalog->persistPhotosetPhotoPage([
            ['id' => 'pivot-photo', 'owner' => '444@N01', 'title' => 'In set'],
        ], '444@N01', 'ps-attach');

        $this->assertSame(1, $count);
        $photo = Photo::query()->where('flickr_photo_id', 'pivot-photo')->first();
        $photoset = Photoset::query()->where('flickr_photoset_id', 'ps-attach')->first();
        $this->assertNotNull($photo);
        $this->assertNotNull($photoset);
        $this->assertDatabaseHas('xflickr_photoset_photo', [
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $photo->id,
        ]);
    }

    public function test_persist_gallery_photo_page_attaches_pivot(): void
    {
        Gallery::query()->create([
            'flickr_gallery_id' => 'gal-attach',
            'owner_nsid' => '555@N01',
            'title' => 'Gallery',
            'photo_count' => 0,
            'raw_payload' => '{}',
        ]);

        $count = $this->catalog->persistGalleryPhotoPage([
            ['id' => 'gal-photo', 'owner' => '555@N01', 'title' => 'In gallery'],
        ], '555@N01', 'gal-attach');

        $this->assertSame(1, $count);
        $photo = Photo::query()->where('flickr_photo_id', 'gal-photo')->first();
        $gallery = Gallery::query()->where('flickr_gallery_id', 'gal-attach')->first();
        $this->assertNotNull($photo);
        $this->assertNotNull($gallery);
        $this->assertDatabaseHas('xflickr_gallery_photo', [
            'xflickr_gallery_id' => $gallery->id,
            'xflickr_photo_id' => $photo->id,
        ]);
    }

    public function test_persist_photoset_photo_page_skips_pivot_when_photoset_missing(): void
    {
        $count = $this->catalog->persistPhotosetPhotoPage([
            ['id' => 'orphan-photo', 'owner' => '666@N01'],
        ], '666@N01', 'missing-ps');

        $this->assertSame(1, $count);
        $this->assertDatabaseMissing('xflickr_photoset_photo', []);
    }

    public function test_persist_contacts_string_value_trimming(): void
    {
        $this->catalog->persistContacts([
            [
                'nsid' => '777@N01',
                'username' => '  ',
                'realname' => 'Name',
            ],
        ]);

        $contact = Contact::query()->where('nsid', '777@N01')->first();
        $this->assertNotNull($contact);
        $this->assertNull($contact->username);
        $this->assertSame('Name', $contact->realname);
    }

    public function test_persist_contacts_records_connection_contacts(): void
    {
        $this->catalog->persistContacts([
            ['nsid' => '888@N01', 'username' => 'zoe'],
        ], 'conn-888');

        $this->assertDatabaseHas('xflickr_connection_contacts', [
            'connection_key' => 'conn-888',
            'contact_nsid' => '888@N01',
        ]);
    }

    public function test_persist_favorites_page_upserts_photos_and_favorites(): void
    {
        $count = app(FlickrFavoritesPersistence::class)->persistPage([
            [
                'id' => 'fav-photo-1',
                'owner' => 'owner@N01',
                'title' => 'Fav',
                'secret' => 's',
                'server' => '1',
                'farm' => 1,
            ],
        ], 'conn-fav', 'subject@N01');

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('xflickr_photos', ['flickr_photo_id' => 'fav-photo-1']);
        $this->assertDatabaseHas('xflickr_favorites', [
            'connection_key' => 'conn-fav',
            'subject_nsid' => 'subject@N01',
            'photo_owner_nsid' => 'owner@N01',
        ]);
        $this->assertDatabaseHas('xflickr_connection_contacts', [
            'connection_key' => 'conn-fav',
            'contact_nsid' => 'owner@N01',
        ]);
    }
}
