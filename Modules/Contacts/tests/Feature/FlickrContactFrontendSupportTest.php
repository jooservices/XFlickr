<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Contacts\Services\ContactListSorter;
use Modules\Contacts\Services\ContactStatsService;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\CrawlRun;
use Modules\Crawler\Models\Favorite;
use Modules\Crawler\Models\Gallery;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Models\Photoset;
use Modules\Crawler\Support\XFlickrConfig;
use Modules\Storage\Models\StorageAccount;
use Modules\Transfer\Jobs\DownloadPhotoJob;
use Modules\Transfer\Jobs\FanOutTransferBatchJob;
use Modules\Transfer\Jobs\UploadPhotoJob;
use Modules\Transfer\Models\StoredFile;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class FlickrContactFrontendSupportTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

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
            ->where('contact.nsid', $contact->nsid)
            ->where('crawl_state.photos.crawled', true)
            ->where('crawl_state.photos.fetched', 1)
            ->missing('contact_detail'));

        $this->get('/flickr/accounts/'.$connection->public_id.'/contacts/missing@N01')
            ->assertNotFound();
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

        $response = $this->getJson('/api/v1/flickr/catalog/favorites?subject_nsid='.$contactNsid);

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

        $response = $this->getJson('/api/v1/flickr/catalog/photos?sort=title&direction=asc');

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

        $response = $this->getJson('/api/v1/flickr/catalog/photos?owner_nsid=111@N01');

        $response->assertOk();
        $response->assertJsonPath('data.0.photosets.0.flickr_id', 'set-1');
        $response->assertJsonPath('data.0.photosets.0.title', 'Travel set');
        $response->assertJsonPath('data.0.galleries.0.flickr_id', 'gal-1');
        $response->assertJsonPath('data.0.galleries.0.title', 'Best shots');
    }

    public function test_catalog_photos_api_includes_download_status(): void
    {
        $photo = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-downloaded',
            'owner_nsid' => '111@N01',
            'title' => 'Downloaded photo',
        ]);

        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-none',
            'owner_nsid' => '111@N01',
            'title' => 'Not downloaded',
        ]);

        $stored = StoredFile::query()->forceCreate([
            'flickr_photo_id' => 'p-downloaded',
            'owner_nsid' => '111@N01',
            'variant' => 'original',
            'status' => 'completed',
            'local_path' => 'flickr/111@N01/photos/p-downloaded_abc.jpg',
            'original_name' => 'p-downloaded_original.jpg',
        ]);

        $response = $this->getJson('/api/v1/flickr/catalog/photos?owner_nsid=111@N01&sort=flickr_photo_id&direction=asc');

        $response->assertOk();
        $response->assertJsonPath('data.0.flickr_photo_id', 'p-downloaded');
        $response->assertJsonPath('data.0.download_status', 'completed');
        $response->assertJsonPath('data.0.stored_file_uuid', $stored->uuid);
        $response->assertJsonPath('data.0.stored_file_view_url', url('/api/v1/stored-files/'.$stored->uuid));
        $response->assertJsonPath('data.1.flickr_photo_id', 'p-none');
        $response->assertJsonPath('data.1.download_status', 'none');
        $response->assertJsonPath('data.1.stored_file_view_url', null);
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

        $photosets = $this->getJson('/api/v1/flickr/catalog/photosets?owner_nsid=111@N01&sort=flickr_photoset_id&direction=asc');
        $photosets->assertOk();
        $photosets->assertJsonPath('data.0.flickr_photoset_id', 'set-cover');
        $photosets->assertJsonPath('data.0.primary_photo_id', 'primary-photo');
        $photosets->assertJsonPath('data.0.primary_secret', 'primary-secret');
        $photosets->assertJsonPath('data.0.primary_server', '7');
        $photosets->assertJsonPath('data.1.flickr_photoset_id', 'set-pivot');
        $photosets->assertJsonPath('data.1.primary_photo_id', 'cover-photo');
        $photosets->assertJsonPath('data.1.primary_secret', 'cover-secret');
        $photosets->assertJsonPath('data.1.primary_server', '42');

        $galleries = $this->getJson('/api/v1/flickr/catalog/galleries?owner_nsid=111@N01');
        $galleries->assertOk();
        $galleries->assertJsonPath('data.0.primary_photo_id', 'gal-primary');
        $galleries->assertJsonPath('data.0.primary_secret', 'gal-secret');
        $galleries->assertJsonPath('data.0.primary_server', '9');
    }

    public function test_catalog_photos_api_filters_by_photoset_id(): void
    {
        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-filter',
            'owner_nsid' => '111@N01',
            'title' => 'Filtered set',
            'photo_count' => 2,
        ]);

        $inSetOne = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-in-1',
            'owner_nsid' => '111@N01',
            'title' => 'In set one',
        ]);
        $inSetTwo = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-in-2',
            'owner_nsid' => '111@N01',
            'title' => 'In set two',
        ]);
        $outside = Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-out',
            'owner_nsid' => '111@N01',
            'title' => 'Outside set',
        ]);

        foreach ([$inSetOne, $inSetTwo] as $photo) {
            DB::table(XFlickrConfig::table('photoset_photo'))->insert([
                'xflickr_photoset_id' => $photoset->id,
                'xflickr_photo_id' => $photo->id,
                'discovered_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/v1/flickr/catalog/photos?photoset_id='.$photoset->id.'&sort=flickr_photo_id&direction=asc');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.flickr_photo_id', 'p-in-1');
        $response->assertJsonPath('data.1.flickr_photo_id', 'p-in-2');
        $this->assertNotContains($outside->flickr_photo_id, collect($response->json('data'))->pluck('flickr_photo_id')->all());
    }

    public function test_catalog_photoset_show_api_returns_presented_photoset(): void
    {
        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-show',
            'owner_nsid' => '111@N01',
            'title' => 'Show set',
            'photo_count' => 3,
        ]);

        $response = $this->getJson('/api/v1/flickr/catalog/photosets/'.$photoset->id);

        $response->assertOk();
        $response->assertJsonPath('data.id', $photoset->id);
        $response->assertJsonPath('data.flickr_photoset_id', 'set-show');
        $response->assertJsonPath('data.title', 'Show set');
    }

    public function test_photoset_show_page_renders_inertia(): void
    {
        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-page',
            'owner_nsid' => '111@N01',
            'title' => 'Page set',
            'photo_count' => 1,
        ]);

        $response = $this->get('/photosets/'.$photoset->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Catalog/Photosets/Show')
            ->where('photoset.id', $photoset->id)
            ->where('photoset.title', 'Page set'));
    }

    public function test_account_scoped_photoset_show_page_renders_inertia(): void
    {
        $connection = $this->createFlickrConnection();
        $photoset = Photoset::query()->forceCreate([
            'flickr_photoset_id' => 'set-account',
            'owner_nsid' => '111@N01',
            'title' => 'Account scoped set',
            'photo_count' => 1,
        ]);

        $response = $this->get('/flickr/accounts/'.$connection->public_id.'/photosets/'.$photoset->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Catalog/Photosets/Show')
            ->where('account.public_id', $connection->public_id)
            ->where('photoset.id', $photoset->id)
            ->where('photoset.title', 'Account scoped set'));
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

    public function test_contacts_bulk_crawl_select_all_with_no_matches_errors(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/contacts/crawl', [
            'select_all' => true,
            'search' => 'definitely-missing-'.fake()->uuid(),
            'types' => ['photos'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No contacts selected.');
    }

    public function test_contacts_bulk_upload_accepts_multiple_contact_nsids(): void
    {
        Bus::fake([DownloadPhotoJob::class, UploadPhotoJob::class]);

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

            StoredFile::query()->forceCreate([
                'flickr_photo_id' => 'p-'.$nsid,
                'owner_nsid' => $nsid,
                'variant' => 'original',
                'status' => 'completed',
                'local_path' => 'flickr/'.$nsid.'/photos/p-'.$nsid.'_abc.jpg',
                'original_name' => 'p-'.$nsid.'_original.jpg',
            ]);
        }

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/upload', [
            'contact_nsids' => ['111@N01', '222@N01'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '2 photo(s) queued for upload across 2 contact(s).');
    }

    public function test_contacts_bulk_download_select_all_queues_matching_contacts(): void
    {
        Bus::fake([DownloadPhotoJob::class, FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $matchNsid = FlickrNsid::fake();
        $otherNsid = FlickrNsid::fake();

        foreach ([[$matchNsid, 'alice'], [$otherNsid, 'bob']] as [$nsid, $username]) {
            Contact::query()->forceCreate([
                'nsid' => $nsid,
                'username' => $username,
                'realname' => $username,
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

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/download', [
            'select_all' => true,
            'search' => 'alice',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 contact download batch(es) queued.');
        Bus::assertDispatched(FanOutTransferBatchJob::class, 1);
    }

    public function test_photos_bulk_download_select_all_requires_owner_nsid_or_contact_filters(): void
    {
        Bus::fake([DownloadPhotoJob::class, FanOutTransferBatchJob::class]);

        $connection = $this->createFlickrConnection();
        $ownerNsid = FlickrNsid::fake();

        Photo::query()->forceCreate([
            'flickr_photo_id' => 'p-owner-1',
            'owner_nsid' => $ownerNsid,
            'title' => 'Photo',
        ]);

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/download', [
            'select_all' => true,
            'owner_nsid' => $ownerNsid,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '1 download batch(es) queued.');
        Bus::assertDispatched(FanOutTransferBatchJob::class, 1);
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

        $stats = app(ContactStatsService::class)->detailStatsFor($connection, $contactNsid);

        $this->assertArrayHasKey('photos', $stats);
        $this->assertSame(0, $stats['photos']['db']);
        $this->assertSame(0, $stats['photos']['with_sizes']);
        $this->assertNull($stats['photos']['in_api']);
    }

    public function test_detail_stats_returns_zero_in_api_after_empty_completed_crawl(): void
    {
        $connection = $this->createFlickrConnection();
        $contactNsid = '555@N01';

        CrawlRun::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'crawl_type' => CrawlType::Photos->value,
            'subject_nsid' => $contactNsid,
            'status' => CrawlRunStatus::Completed,
            'photos_discovered' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $stats = app(ContactStatsService::class)->detailStatsFor($connection, $contactNsid);

        $this->assertSame(0, $stats['photos']['in_api']);
    }

    public function test_contact_crawl_is_blocked_when_token_invalid(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate([
            'nsid' => '777@N01',
            'username' => 'blocked',
            'realname' => 'Blocked',
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        $this->mockFlickrTokenHealth(valid: false);

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid.'/crawl', [
            'types' => ['photos'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_contact_crawl_is_blocked_when_global_pause_is_active(): void
    {
        $connection = $this->createFlickrConnection();
        $contact = Contact::query()->forceCreate([
            'nsid' => FlickrNsid::fake(),
            'username' => fake()->userName(),
            'realname' => fake()->name(),
        ]);

        ConnectionContact::query()->forceCreate([
            'connection_key' => $connection->connection_key,
            'contact_nsid' => $contact->nsid,
        ]);

        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $response = $this->post('/flickr/accounts/'.$connection->public_id.'/contacts/'.$contact->nsid.'/crawl', [
            'types' => ['photos'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Global crawl pause is active. Resume from the header to start crawls.');
        $this->assertDatabaseMissing('xflickr_crawl_runs', [
            'connection_key' => $connection->connection_key,
        ]);

        RuntimeConfig::forget('xflickr.global_pause');
        RuntimeConfig::refresh();
    }
}
