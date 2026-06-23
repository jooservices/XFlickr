<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\UploadPhotoJob;
use App\Models\StorageAccount;
use App\Services\Flickr\ContactCatalogDetailStatsService;
use App\Services\Flickr\ContactListSorter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use JOOservices\XFlickrCrawler\Enums\CrawlRunStatus;
use JOOservices\XFlickrCrawler\Enums\CrawlType;
use JOOservices\XFlickrCrawler\Models\ConnectionContact;
use JOOservices\XFlickrCrawler\Models\Contact;
use JOOservices\XFlickrCrawler\Models\CrawlRun;
use JOOservices\XFlickrCrawler\Models\Favorite;
use JOOservices\XFlickrCrawler\Models\Gallery;
use JOOservices\XFlickrCrawler\Models\Photo;
use JOOservices\XFlickrCrawler\Models\Photoset;
use JOOservices\XFlickrCrawler\Support\XFlickrConfig;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrContactFrontendSupportTest extends TestCase
{
    use CreatesFlickrConnection;
    use RefreshDatabase;

    public function test_contacts_index_supports_sort_query_params(): void
    {
        $connection = $this->createFlickrConnection();

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
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-beta-1',
            'owner_nsid' => $beta->nsid,
            'title' => 'B1',
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-beta-2',
            'owner_nsid' => $beta->nsid,
            'title' => 'B2',
        ]);

        $response = $this->get(
            '/flickr/accounts/'.$connection->public_id.'/contacts?sort=photos_count&direction=desc',
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Contacts/Index')
            ->where('filters.sort', 'photos_count')
            ->where('filters.direction', 'desc')
            ->where('contacts.0.nsid', $beta->nsid)
            ->where('contacts.1.nsid', $alpha->nsid));
    }

    public function test_contact_show_includes_catalog_stats(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate([
            'nsid' => '333@N01',
            'username' => 'gamma',
            'realname' => 'Gamma',
            'friend' => true,
            'family' => false,
            'raw_payload' => ['pathalias' => 'gamma-alias'],
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-gamma-1',
            'owner_nsid' => $contact->nsid,
            'title' => 'G1',
            'raw_payload' => ['sizes' => ['label' => 'Square']],
        ]);

        CrawlRun::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Photos->value,
            'subject_nsid' => $contact->nsid,
            'status' => CrawlRunStatus::Completed,
            'photos_discovered' => 42,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->get('/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Contacts/Show')
            ->has('catalog_stats.photos', fn ($stats) => $stats
                ->where('db', 1)
                ->where('with_sizes', 1)
                ->where('in_api', 42)
                ->etc())
            ->where('contact_detail.nsid', $contact->nsid));
    }

    public function test_favorites_catalog_api_filters_by_subject_nsid(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = '444@N01';

        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-fav-1',
            'owner_nsid' => 'other@N01',
            'title' => 'Fav photo',
        ]);

        Favorite::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => $contactNsid,
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => $photo->owner_nsid,
            'discovered_at' => now(),
        ]);

        Favorite::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'subject_nsid' => 'other-contact@N01',
            'xflickr_photo_id' => $photo->id,
            'photo_owner_nsid' => $photo->owner_nsid,
            'discovered_at' => now(),
        ]);

        $response = $this->getJson('/api/flickr/catalog/favorites?subject_nsid='.$contactNsid);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.subject_nsid', $contactNsid);
    }

    public function test_catalog_photos_api_supports_sort(): void
    {
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-alpha',
            'owner_nsid' => '111@N01',
            'title' => 'Alpha photo',
        ]);
        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-zulu',
            'owner_nsid' => '222@N01',
            'title' => 'Zulu photo',
        ]);

        $response = $this->getJson('/api/flickr/catalog/photos?sort=title&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('meta.sort', 'title');
        $response->assertJsonPath('meta.direction', 'asc');
        $response->assertJsonPath('data.0.title', 'Alpha photo');
        $response->assertJsonPath('data.1.title', 'Zulu photo');
    }

    public function test_catalog_photos_api_includes_photoset_and_gallery_memberships(): void
    {
        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-member',
            'owner_nsid' => '111@N01',
            'title' => 'Member photo',
        ]);

        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-1',
            'owner_nsid' => '111@N01',
            'title' => 'Travel set',
            'photo_count' => 1,
        ]);
        $gallery = Gallery::query()->forceCreate([
            'flickr_gallery_id' => 'gal-1',
            'owner_nsid' => '111@N01',
            'title' => 'Best shots',
            'photo_count' => 1,
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photoset->id,
            'xflickr_photo_id' => $photo->id,
            'discovered_at' => now(),
        ]);
        DB::table(XFlickrConfig::table('gallery_photo'))->insert([
            'xflickr_gallery_id' => $gallery->id,
            'xflickr_photo_id' => $photo->id,
            'discovered_at' => now(),
        ]);

        $response = $this->getJson('/api/flickr/catalog/photos?owner_nsid=111@N01');

        $response->assertOk();
        $response->assertJsonPath('data.0.photosets.0.flickr_id', 'set-1');
        $response->assertJsonPath('data.0.photosets.0.title', 'Travel set');
        $response->assertJsonPath('data.0.galleries.0.flickr_id', 'gal-1');
        $response->assertJsonPath('data.0.galleries.0.title', 'Best shots');
    }

    public function test_catalog_photosets_and_galleries_api_include_thumbnail_fields(): void
    {
        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => 'cover-photo',
            'owner_nsid' => '111@N01',
            'title' => 'Cover',
            'secret' => 'cover-secret',
            'server' => '42',
        ]);

        Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-cover',
            'owner_nsid' => '111@N01',
            'title' => 'Cover set',
            'photo_count' => 1,
            'raw_payload' => [
                'id' => 'set-cover',
                'primary' => 'primary-photo',
                'secret' => 'primary-secret',
                'server' => '7',
            ],
        ]);

        $photosetWithPivot = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-pivot',
            'owner_nsid' => '111@N01',
            'title' => 'Pivot set',
            'photo_count' => 1,
            'raw_payload' => [],
        ]);

        DB::table(XFlickrConfig::table('photoset_photo'))->insert([
            'xflickr_photoset_id' => $photosetWithPivot->id,
            'xflickr_photo_id' => $photo->id,
            'discovered_at' => now(),
        ]);

        Gallery::query()->forceCreate([
            'flickr_gallery_id' => 'gal-cover',
            'owner_nsid' => '111@N01',
            'title' => 'Cover gallery',
            'photo_count' => 1,
            'raw_payload' => [
                'id' => 'gal-cover',
                'primary_photo_id' => 'gal-primary',
                'primary_photo_secret' => 'gal-secret',
                'primary_photo_server' => '9',
            ],
        ]);

        $photosets = $this->getJson('/api/flickr/catalog/photosets?owner_nsid=111@N01&sort=flickr_photoset_id&direction=asc');
        $photosets->assertOk();
        $photosets->assertJsonPath('data.0.flickr_photoset_id', 'set-cover');
        $photosets->assertJsonPath('data.0.primary_photo_id', 'primary-photo');
        $photosets->assertJsonPath('data.0.primary_secret', 'primary-secret');
        $photosets->assertJsonPath('data.0.primary_server', '7');
        $photosets->assertJsonPath('data.1.flickr_photoset_id', 'set-pivot');
        $photosets->assertJsonPath('data.1.primary_photo_id', 'cover-photo');
        $photosets->assertJsonPath('data.1.primary_secret', 'cover-secret');
        $photosets->assertJsonPath('data.1.primary_server', '42');

        $galleries = $this->getJson('/api/flickr/catalog/galleries?owner_nsid=111@N01');
        $galleries->assertOk();
        $galleries->assertJsonPath('data.0.primary_photo_id', 'gal-primary');
        $galleries->assertJsonPath('data.0.primary_secret', 'gal-secret');
        $galleries->assertJsonPath('data.0.primary_server', '9');
    }

    public function test_contacts_bulk_crawl_requires_selection(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/contacts/crawl', [
            'contact_nsids' => [],
            'types' => ['photos'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No contacts selected.');
    }

    public function test_contacts_bulk_upload_accepts_multiple_contact_nsids(): void
    {
        Bus::fake([UploadPhotoJob::class]);

        $connection = $this->createFlickrConnection();

        StorageAccount::query()->forceCreate([
            'provider' => 'google_drive',
            'label' => 'Default',
            'is_default' => true,
            'credentials' => [],
        ]);

        foreach (['111@N01', '222@N01'] as $nsid) {
            Contact::query()->forceCreate([
                'nsid' => $nsid,
                'username' => $nsid,
                'realname' => $nsid,
            ]);

            ConnectionContact::query()->forceCreate([
                'connection_key' => $connection->connection_key,
                'contact_nsid' => $nsid,
            ]);

            Photo::query()->forceCreate([
                'flickr_photo_id' => 'p-'.$nsid,
                'owner_nsid' => $nsid,
                'title' => 'Photo',
            ]);
        }

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/upload', [
            'contact_nsids' => ['111@N01', '222@N01'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 photo(s) queued for upload across 2 contact(s).');
    }

    public function test_contact_list_sorter_allowlist(): void
    {
        $this->assertSame(
            [
                'nsid',
                'username',
                'photos_count',
                'favorites_count',
                'photosets_count',
                'galleries_count',
                'downloads_count',
            ],
            ContactListSorter::SORTABLE_COLUMNS,
        );
    }

    public function test_detail_stats_service_returns_expected_shape(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = '555@N01';

        $stats = app(ContactCatalogDetailStatsService::class)->forContact($connection, $contactNsid);

        $this->assertArrayHasKey('photos', $stats);
        $this->assertSame(0, $stats['photos']['db']);
        $this->assertSame(0, $stats['photos']['with_sizes']);
        $this->assertNull($stats['photos']['in_api']);
    }
}
