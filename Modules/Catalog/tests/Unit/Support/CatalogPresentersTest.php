<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Unit\Support;

use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Support\CollectionCatalogPresenter;
use Modules\Catalog\Support\PhotoCatalogPresenter;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Transfer\Enums\StoredFileStatus;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CatalogPresentersTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_photo_presenter_includes_memberships_and_download_meta(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
        ]));

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Set title',
            'photo_count' => 1,
        ]);
        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Gallery title',
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

        $stored = StoredFile::query()->create([
            'uuid' => fake()->uuid(),
            'flickr_photo_id' => $photo->flickr_photo_id,
            'owner_nsid' => $ownerNsid,
            'variant' => 'original',
            'status' => StoredFileStatus::Completed->value,
            'original_name' => $photo->flickr_photo_id.'_original.jpg',
        ]);

        $presented = app(PhotoCatalogPresenter::class)->presentPage([$photo]);

        $this->assertCount(1, $presented);
        $this->assertCount(1, $presented[0]['photosets']);
        $this->assertCount(1, $presented[0]['galleries']);
        $this->assertSame(StoredFileStatus::Completed->value, $presented[0]['download_status']);
        $this->assertSame($stored->uuid, $presented[0]['stored_file_uuid']);
        $this->assertStringContainsString('/api/v1/stored-files/', (string) $presented[0]['stored_file_view_url']);
    }

    public function test_photo_presenter_download_progress_covers_pending_and_missing(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
        ]));
        $missingId = (string) fake()->unique()->numerify('#########');

        StoredFile::query()->create([
            'flickr_photo_id' => $photo->flickr_photo_id,
            'owner_nsid' => $ownerNsid,
            'variant' => 'original',
            'status' => StoredFileStatus::Pending->value,
            'original_name' => $photo->flickr_photo_id.'_original',
        ]);

        $progress = app(PhotoCatalogPresenter::class)->presentDownloadProgress([
            $photo->flickr_photo_id,
            $missingId,
            '',
        ]);

        $this->assertCount(2, $progress);
        $this->assertSame(StoredFileStatus::Pending->value, $progress[0]['download_status']);
        $this->assertNull($progress[0]['stored_file_view_url']);
        $this->assertSame('none', $progress[1]['download_status']);
        $this->assertSame($missingId, $progress[1]['flickr_photo_id']);
    }

    public function test_collection_presenter_uses_raw_payload_primary_photo(): void
    {
        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Payload set',
            'photo_count' => 1,
            'raw_payload' => [
                'primary' => '99999',
                'secret' => 'zzzzzz',
                'server' => '4321',
            ],
        ]);

        $presented = app(CollectionCatalogPresenter::class)->presentPhotoset($photoset);

        $this->assertSame('99999', $presented['primary_photo_id']);
        $this->assertSame('zzzzzz', $presented['primary_secret']);
    }

    public function test_collection_presenter_falls_back_to_first_pivot_photo(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'flickr_photo_id' => 'fallback-photo',
            'secret' => 'sec123',
            'server' => '777',
        ]));
        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Fallback gallery',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('gallery_photo'))->insert([
            'xflickr_gallery_id' => $gallery->id,
            'xflickr_photo_id' => $photo->id,
            'discovered_at' => now(),
        ]);

        $presented = app(CollectionCatalogPresenter::class)->presentGalleries([$gallery]);

        $this->assertSame('fallback-photo', $presented[0]['primary_photo_id']);
        $this->assertSame('sec123', $presented[0]['primary_secret']);
    }

    public function test_collection_presenter_returns_empty_arrays_for_empty_input(): void
    {
        $presenter = app(CollectionCatalogPresenter::class);

        $this->assertSame([], $presenter->presentPhotosets([]));
        $this->assertSame([], $presenter->presentGalleries([]));
    }

    public function test_collection_presenter_parses_xml_style_primary_values(): void
    {
        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'XML payload',
            'photo_count' => 1,
            'raw_payload' => [
                'primary' => ['_content' => '12345'],
                'secret' => ['_content' => 'abcde'],
                'server' => ['_content' => '999'],
            ],
        ]);

        $presented = app(CollectionCatalogPresenter::class)->presentPhotoset($photoset);

        $this->assertSame('12345', $presented['primary_photo_id']);
        $this->assertSame('abcde', $presented['primary_secret']);
        $this->assertSame('999', $presented['primary_server']);
    }

    public function test_collection_presenter_skips_invalid_pivot_photo_rows(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $validPhoto = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'flickr_photo_id' => 'valid-photo',
            'secret' => 'sec999',
            'server' => '888',
        ]));
        $invalidPhoto = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'flickr_photo_id' => '',
            'secret' => '',
            'server' => '',
        ]));
        $gallery = Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Pivot gallery',
            'photo_count' => 2,
        ]);

        DB::table(XFlickrConfig::table('gallery_photo'))->insert([
            [
                'xflickr_gallery_id' => $gallery->id,
                'xflickr_photo_id' => $invalidPhoto->id,
                'discovered_at' => now()->subMinute(),
            ],
            [
                'xflickr_gallery_id' => $gallery->id,
                'xflickr_photo_id' => $validPhoto->id,
                'discovered_at' => now(),
            ],
        ]);

        $presented = app(CollectionCatalogPresenter::class)->presentGalleries([$gallery]);

        $this->assertSame('valid-photo', $presented[0]['primary_photo_id']);
    }
}
