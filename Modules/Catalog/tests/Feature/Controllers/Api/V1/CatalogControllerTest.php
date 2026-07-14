<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Feature\Controllers\Api\V1;

use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\Validator;
use Modules\Catalog\Http\Requests\Api\Catalog\ListFavoritesRequest;
use Modules\Catalog\Http\Requests\Api\Catalog\ListPhotosRequest;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CatalogControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_photos_endpoint_returns_paginated_catalog_rows(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'title' => 'Catalog photo',
        ]));

        $response = $this->getJson('/api/v1/flickr/catalog/photos?owner_nsid='.$ownerNsid);

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.flickr_photo_id', $photo->flickr_photo_id);
    }

    public function test_photos_endpoint_falls_back_to_default_sort_for_unknown_column(): void
    {
        $response = $this->getJson('/api/v1/flickr/catalog/photos?sort=not-a-column');

        $response->assertOk();
        $response->assertJsonPath('meta.sort', 'id');
    }

    public function test_show_photoset_returns_not_found_for_missing_id(): void
    {
        $response = $this->getJson('/api/v1/flickr/catalog/photosets/999999');

        $response->assertNotFound();
    }

    public function test_show_photoset_returns_presented_row(): void
    {
        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Show set',
            'photo_count' => 0,
        ]);

        $response = $this->getJson('/api/v1/flickr/catalog/photosets/'.$photoset->id);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Show set');
    }

    public function test_photosets_and_galleries_endpoints_filter_by_owner(): void
    {
        $ownerNsid = FlickrNsid::fake();

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Owner set',
            'photo_count' => 0,
        ]);
        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Owner gallery',
            'photo_count' => 0,
        ]);

        $photosets = $this->getJson('/api/v1/flickr/catalog/photosets?owner_nsid='.$ownerNsid);
        $galleries = $this->getJson('/api/v1/flickr/catalog/galleries?owner_nsid='.$ownerNsid);

        $photosets->assertOk()->assertJsonPath('meta.total', 1);
        $galleries->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_favorites_endpoint_filters_by_connection_and_subject(): void
    {
        $connection = $this->createFlickrConnection();
        $subjectNsid = FlickrNsid::fake();
        $photo = Photo::query()->forceCreate(PhotoFactory::new()->definition());

        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => $photo->owner_nsid,
            'discovered_at' => now(),
        ]);

        $response = $this->getJson(
            '/api/v1/flickr/catalog/favorites?connection_key='.$connection->connection_key.'&subject_nsid='.$subjectNsid,
        );

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_list_photos_request_normalizes_invalid_photoset_id_to_null(): void
    {
        $request = ListPhotosRequest::create('/api/v1/flickr/catalog/photos', 'GET', [
            'photoset_id' => '0',
        ]);
        $request->setContainer($this->app);

        $this->assertNull($request->photosetId());
    }

    public function test_list_favorites_request_maps_owner_nsid_alias(): void
    {
        $subject = FlickrNsid::fake();
        $request = ListFavoritesRequest::create('/api/v1/flickr/catalog/favorites', 'GET', [
            'owner_nsid' => $subject,
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame($subject, $request->subjectNsid());
    }

    public function test_list_photos_request_rejects_negative_per_page(): void
    {
        $validator = Validator::make(
            ['per_page' => 0],
            (new ListPhotosRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('per_page', $validator->errors()->toArray());
    }
}
