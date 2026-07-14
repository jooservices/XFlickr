<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\Crawler\PhotoQueryRepository;
use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\ContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class PhotoQueryRepositoryTest extends TestCase
{
    use CreatesFlickrConnection;
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

    public function test_list_by_owner_nsid_and_exists_for_owner(): void
    {
        $ownerNsid = FlickrNsid::fake();
        $photo = Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
        ]));

        $repository = app(PhotoQueryRepository::class);

        $this->assertTrue($repository->existsForOwnerNsid($ownerNsid));
        $this->assertSame($photo->id, $repository->listByOwnerNsid($ownerNsid)->first()?->id);
    }

    public function test_find_and_list_by_flickr_photo_ids(): void
    {
        $photo = Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'flickr_photo_id' => 'find-me',
        ]));

        $repository = app(PhotoQueryRepository::class);

        $this->assertSame($photo->id, $repository->findByFlickrPhotoId('find-me')?->id);
        $this->assertCount(1, $repository->listByFlickrPhotoIds(['find-me']));
        $this->assertCount(0, $repository->listByFlickrPhotoIds([]));
    }

    public function test_counts_for_connection_and_grouped_by_connection(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = ContactFactory::new()->create();
        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $contact->nsid,
            'raw_payload' => ['sizes' => ['large' => ['url' => 'https://example.com/l.jpg']]],
        ]));
        Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $contact->nsid,
        ]));

        $repository = app(PhotoQueryRepository::class);

        $this->assertSame(2, $repository->countForConnection($connection->connection_key));
        $this->assertSame(1, $repository->countWithSizesForConnection($connection->connection_key));
        $this->assertSame(
            [$connection->connection_key => 2],
            $repository->countsGroupedByConnection([$connection->connection_key]),
        );
        $this->assertSame(
            [$connection->connection_key => 1],
            $repository->countsWithSizesGroupedByConnection([$connection->connection_key]),
        );
    }

    public function test_counts_by_owner_nsids_and_with_sizes_for_owner(): void
    {
        $ownerNsid = FlickrNsid::fake();

        Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $ownerNsid,
            'raw_payload' => ['sizes' => ['large' => ['url' => 'https://example.com/l.jpg']]],
        ]));

        $repository = app(PhotoQueryRepository::class);

        $this->assertSame(1, $repository->countWithSizesForOwner($ownerNsid));
        $this->assertSame([$ownerNsid => 1], $repository->countsByOwnerNsids([$ownerNsid]));
        $this->assertSame([], $repository->countsByOwnerNsids([]));
    }

    public function test_update_raw_payload_persists_sizes(): void
    {
        $photo = Photo::query()->create(array_merge(PhotoFactory::new()->definition(), [
            'raw_payload' => [],
        ]));

        app(PhotoQueryRepository::class)->updateRawPayload($photo, ['sizes' => ['medium' => []]]);

        $this->assertSame(['sizes' => ['medium' => []]], $photo->fresh()?->raw_payload);
    }
}
