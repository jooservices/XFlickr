<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Crawler\CatalogQueryRepository;
use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class CatalogQueryRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    private CatalogQueryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(CatalogQueryRepository::class);
    }

    public function test_paginate_photos_filters_by_owner_and_photoset(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $otherOwner = FlickrNsid::fake();

        $inSet = $this->createPhoto(['owner_nsid' => $ownerNsid, 'title' => 'In set']);
        $ownerOnly = $this->createPhoto(['owner_nsid' => $ownerNsid, 'title' => 'Owner only']);
        $this->createPhoto(['owner_nsid' => $otherOwner, 'title' => 'Other owner']);

        $photoset = Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Trip',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $inSet->id,
        ]);

        $byOwner = $this->repository->paginatePhotos($ownerNsid, null, 'title', 'asc', 50, 1);
        $this->assertSame(2, $byOwner->total());

        $bySet = $this->repository->paginatePhotos($ownerNsid, $photoset->id, 'title', 'asc', 50, 1);
        $this->assertSame(1, $bySet->total());
        $this->assertSame($inSet->id, $bySet->items()[0]->id);
    }

    public function test_paginate_collections_and_favorites_apply_filters(): void
    {
        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();
        $subjectNsid = FlickrNsid::fake();

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Alpha set',
            'photo_count' => 0,
        ]);
        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => FlickrNsid::fake(),
            'title' => 'Other set',
            'photo_count' => 0,
        ]);

        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $ownerNsid,
            'title' => 'Alpha gallery',
            'photo_count' => 0,
        ]);

        $photo = $this->createPhoto(['owner_nsid' => $ownerNsid]);
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $subjectNsid,
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => $ownerNsid,
            'discovered_at' => now(),
        ]);

        $this->assertSame(1, $this->repository->paginatePhotosets($ownerNsid, 'title', 'asc', 50, 1)->total());
        $this->assertSame(1, $this->repository->paginateGalleries($ownerNsid, 'title', 'asc', 50, 1)->total());
        $this->assertSame(
            1,
            $this->repository->paginateFavorites($connection->connection_key, $subjectNsid, 'id', 'asc', 50, 1)->total(),
        );
    }

    public function test_grouped_counts_return_empty_for_empty_key_lists(): void
    {
        $this->assertSame([], $this->repository->photosetCountsGroupedByConnection([]));
        $this->assertSame([], $this->repository->galleryCountsGroupedByConnection([]));
        $this->assertSame([], $this->repository->favoriteCountsGroupedByConnection([]));
        $this->assertSame([], $this->repository->photosetCountsByOwnerNsids([]));
        $this->assertSame([], $this->repository->galleryCountsByOwnerNsids([]));
        $this->assertSame([], $this->repository->favoriteCountsBySubjectNsids('conn@N01', []));
    }

    public function test_grouped_counts_aggregate_by_connection_and_owner(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = FlickrNsid::fake();

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contactNsid,
        ]);

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $contactNsid,
            'title' => 'Contact set',
            'photo_count' => 1,
        ]);
        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $contactNsid,
            'title' => 'Contact gallery',
            'photo_count' => 1,
        ]);

        $photo = $this->createPhoto(['owner_nsid' => $contactNsid]);
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => $contactNsid,
            'discovered_at' => now(),
        ]);

        $this->assertSame(
            1,
            $this->repository->countPhotosetsForConnection($connection->connection_key),
        );
        $this->assertSame(
            1,
            $this->repository->countGalleriesForConnection($connection->connection_key),
        );
        $this->assertSame(
            [$connection->connection_key => 1],
            $this->repository->photosetCountsGroupedByConnection([$connection->connection_key]),
        );
        $this->assertSame(
            [$connection->connection_key => 1],
            $this->repository->favoriteCountsGroupedByConnection([$connection->connection_key]),
        );
        $this->assertSame(
            [$contactNsid => 1],
            $this->repository->photosetCountsByOwnerNsids([$contactNsid]),
        );
        $this->assertSame(
            [$contactNsid => 1],
            $this->repository->favoriteCountsBySubjectNsids($connection->connection_key, [$contactNsid]),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPhoto(array $attributes = []): Photo
    {
        return Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), $attributes));
    }
}
