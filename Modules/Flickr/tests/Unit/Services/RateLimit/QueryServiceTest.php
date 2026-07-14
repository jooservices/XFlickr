<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services\RateLimit;

use Database\Factories\Crawler\ConnectionContactFactory;
use Database\Factories\Crawler\PhotoFactory;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Flickr\Services\RateLimit\QueryService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;

final class QueryServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_snapshot_returns_empty_accounts_when_no_connections_exist(): void
    {
        $snapshot = app(QueryService::class)->snapshot();

        $this->assertSame([], $snapshot['accounts']);
        $this->assertNull($snapshot['active_connection_key']);
        $this->assertNotEmpty($snapshot['generated_at']);
    }

    public function test_snapshot_includes_catalog_counts_per_connection(): void
    {
        $connection = $this->createFlickrConnection(['is_active' => true]);
        $contactNsid = FlickrNsid::fake();

        ConnectionContactFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contactNsid,
        ]);

        Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $contactNsid,
        ]));

        Photoset::query()->create([
            'flickr_photoset_id' => fake()->uuid(),
            'owner_nsid' => $contactNsid,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        Gallery::query()->create([
            'flickr_gallery_id' => fake()->uuid(),
            'owner_nsid' => $contactNsid,
            'title' => fake()->words(2, true),
            'photo_count' => 1,
        ]);

        $favoritePhoto = Photo::query()->forceCreate(array_merge(PhotoFactory::new()->definition(), [
            'owner_nsid' => $contactNsid,
        ]));
        Favorite::query()->create([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'xflickr_photo_id' => $favoritePhoto->id,
            'photo_owner_nsid' => $contactNsid,
            'discovered_at' => now(),
        ]);

        $snapshot = app(QueryService::class)->snapshot();

        $this->assertCount(1, $snapshot['accounts']);
        $this->assertSame($connection->connection_key, $snapshot['active_connection_key']);
        $this->assertSame($connection->connection_key, $snapshot['accounts'][0]['account']['nsid']);
        $this->assertSame(1, $snapshot['accounts'][0]['catalog_counts']['contacts_db']);
        $this->assertSame(2, $snapshot['accounts'][0]['catalog_counts']['photos_db']);
        $this->assertSame(1, $snapshot['accounts'][0]['catalog_counts']['photosets_db']);
        $this->assertSame(1, $snapshot['accounts'][0]['catalog_counts']['galleries_db']);
        $this->assertSame(1, $snapshot['accounts'][0]['catalog_counts']['favorites_db']);
        $this->assertArrayHasKey('requests_used', $snapshot['accounts'][0]['rate_limit']);
    }
}
