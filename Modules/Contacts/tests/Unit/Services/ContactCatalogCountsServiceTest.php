<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Services;

use Database\Factories\Crawler\PhotoFactory;
use Modules\Contacts\Services\ContactCatalogCountsService;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactCatalogCountsServiceTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_for_contacts_returns_empty_array_for_empty_input(): void
    {
        $connection = $this->createFlickrConnection();

        $counts = app(ContactCatalogCountsService::class)->forContacts($connection, []);

        $this->assertSame([], $counts);
    }

    public function test_for_contacts_returns_zero_counts_for_contacts_without_catalog_rows(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        $counts = app(ContactCatalogCountsService::class)->forContacts($connection, [$contactNsid]);

        $this->assertSame([
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ], $counts[$contactNsid]);
    }

    public function test_for_contacts_aggregates_owner_and_connection_scoped_counts(): void
    {
        $connection = $this->createFlickrConnection();
        $otherConnection = $this->createFlickrConnection();
        $withCatalog = FlickrNsid::fake();
        $withoutCatalog = FlickrNsid::fake();

        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));
        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $withCatalog,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $withCatalog,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        $favoritePhoto = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $withCatalog,
        ]));
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $withCatalog,
            'xflickr_photo_id' => $favoritePhoto->id,
            'photo_owner_nsid' => $withCatalog,
            'discovered_at' => now(),
        ]);
        Favorite::query()->create([
            'connection_key' => $otherConnection->connection_key,
            'subject_nsid' => $withCatalog,
            'xflickr_photo_id' => $favoritePhoto->id,
            'photo_owner_nsid' => $withCatalog,
            'discovered_at' => now(),
        ]);

        $counts = app(ContactCatalogCountsService::class)->forContacts(
            $connection,
            [$withCatalog, $withoutCatalog],
        );

        $this->assertSame([
            'photos' => 3,
            'photosets' => 1,
            'galleries' => 1,
            'favorites' => 1,
        ], $counts[$withCatalog]);
        $this->assertSame([
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ], $counts[$withoutCatalog]);
    }
}
