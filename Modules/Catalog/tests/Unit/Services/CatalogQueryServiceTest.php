<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Unit\Services;

use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Services\CatalogQueryService;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CatalogQueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private CatalogQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CatalogQueryService::class);
    }

    public function test_photos_returns_presented_rows_and_meta(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'title' => 'Sunset',
        ]));

        $result = $this->service->photos($ownerNsid, null, 'title', 'asc', 25, 1);

        $this->assertCount(1, $result['data']);
        $this->assertSame($photo->flickr_photo_id, $result['data'][0]['flickr_photo_id']);
        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame('title', $result['meta']['sort']);
        $this->assertSame('asc', $result['meta']['direction']);
    }

    public function test_photoset_returns_null_when_missing(): void
    {
        $this->assertNull($this->service->photoset(999_999));
    }

    public function test_photoset_returns_presented_collection(): void
    {
        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Weekend',
            'photo_count' => 0,
            'raw_payload' => [
                'primary' => '12345',
                'secret' => 'abcdef',
                'server' => '1234',
            ],
        ]);

        $presented = $this->service->photoset($photoset->id);

        $this->assertNotNull($presented);
        $this->assertSame('Weekend', $presented['title']);
        $this->assertSame('12345', $presented['primary_photo_id']);
    }

    public function test_photos_filters_by_photoset_membership(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $inSet = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
        ]));
        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
        ]));

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Filtered',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $inSet->id,
        ]);

        $result = $this->service->photos($ownerNsid, $photoset->id, 'id', 'asc', 25, 1);

        $this->assertCount(1, $result['data']);
        $this->assertSame($inSet->id, $result['data'][0]['id']);
    }
}
