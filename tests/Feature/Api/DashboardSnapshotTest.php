<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use JOOservices\XFlickrCrawler\Models\ConnectionContact;
use JOOservices\XFlickrCrawler\Models\Contact;
use JOOservices\XFlickrCrawler\Models\Gallery;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Models\Photoset;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class DashboardSnapshotTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_snapshot_includes_catalog_counts_per_account(): void
    {
        $connection = $this->createFlickrConnection([
            'connection_key' => '12037949629@N01',
            'username' => 'testuser',
            'fullname' => 'Test User',
        ]);

        $alpha = Contact::query()->forceCreate([
            'nsid' => '111@N01',
            'username' => 'alpha',
            'realname' => 'Alpha',
        ]);
        $beta = Contact::query()->forceCreate([
            'nsid' => '222@N01',
            'username' => 'beta',
            'realname' => 'Beta',
        ]);

        foreach ([$alpha, $beta] as $contact) {
            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $contact->nsid,
            ]);
        }

        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-alpha-1',
            'owner_nsid' => $alpha->nsid,
            'title' => 'A1',
            'raw_payload' => ['sizes' => ['Large' => ['url' => 'https://example.test/a1.jpg']]],
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-alpha-2',
            'owner_nsid' => $alpha->nsid,
            'title' => 'A2',
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-beta-1',
            'owner_nsid' => $beta->nsid,
            'title' => 'B1',
            'raw_payload' => ['sizes' => ['Large' => ['url' => 'https://example.test/b1.jpg']]],
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-unlinked-1',
            'owner_nsid' => '999@N01',
            'title' => 'Unlinked',
        ]);

        Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-alpha-1',
            'owner_nsid' => $alpha->nsid,
            'title' => 'Alpha set',
            'photo_count' => 1,
        ]);
        Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-unlinked-1',
            'owner_nsid' => '999@N01',
            'title' => 'Unlinked set',
            'photo_count' => 1,
        ]);

        Gallery::query()->forceCreate([
            'flickr_gallery_id' => 'gal-beta-1',
            'owner_nsid' => $beta->nsid,
            'title' => 'Beta gallery',
            'photo_count' => 1,
        ]);
        Gallery::query()->forceCreate([
            'flickr_gallery_id' => 'gal-unlinked-1',
            'owner_nsid' => '999@N01',
            'title' => 'Unlinked gallery',
            'photo_count' => 1,
        ]);

        Cache::forget('xflickr:dashboard:snapshot');

        $response = $this->getJson('/api/dashboard/snapshot');

        $response->assertOk();
        $response->assertJsonPath('data.accounts.0.contacts_db', 2);
        $response->assertJsonPath('data.accounts.0.photos_db', 3);
        $response->assertJsonPath('data.accounts.0.photos_with_sizes', 2);
        $response->assertJsonPath('data.accounts.0.photosets_db', 1);
        $response->assertJsonPath('data.accounts.0.galleries_db', 1);
    }
}
