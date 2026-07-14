<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Modules\Flickr\Http\Requests\BeginFlickrOAuthRequest;
use Modules\Flickr\Http\Requests\CrawlFlickrAccountRequest;
use Modules\Settings\Http\Requests\RuntimeConfigPathRequest;
use Modules\Settings\Http\Requests\StoreFlickrAppProfileRequest;
use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Http\Requests\BeginStorageOAuthRequest;
use Modules\Storage\Http\Requests\ReauthorizeStorageRequest;
use Modules\Storage\Http\Requests\StorageOAuthCallbackRequest;
use Modules\Transfer\Http\Requests\QueuePhotoDownloadRequest;
use Modules\Transfer\Http\Requests\QueuePhotoUploadRequest;
use Tests\TestCase;

final class FormRequestValidationTest extends TestCase
{
    public function test_store_flickr_app_profile_requires_api_credentials(): void
    {
        $validator = Validator::make([], (new StoreFlickrAppProfileRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_key', $validator->errors()->toArray());
        $this->assertArrayHasKey('api_secret', $validator->errors()->toArray());
    }

    public function test_crawl_account_request_coerces_string_types_to_array(): void
    {
        $request = CrawlFlickrAccountRequest::create('/crawl-runs', 'POST', ['types' => 'photos']);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame(['photos'], $request->input('types'));
    }

    public function test_crawl_account_request_rejects_invalid_type(): void
    {
        $validator = Validator::make(
            ['types' => ['not-a-crawl-type']],
            (new CrawlFlickrAccountRequest)->rules(),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('types.0', $validator->errors()->toArray());
    }

    public function test_queue_photo_download_request_normalizes_contact_nsids_string(): void
    {
        $request = QueuePhotoDownloadRequest::create('/download', 'POST', [
            'contact_nsids' => '12345@N00',
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame(['12345@N00'], $request->contactNsids());
    }

    public function test_queue_photo_download_request_single_photo_takes_priority(): void
    {
        $request = QueuePhotoDownloadRequest::create('/download', 'POST', [
            'flickr_photo_id' => 'photo-1',
            'contact_nsid' => '12345@N00',
            'contact_nsids' => ['67890@N01'],
        ]);
        $request->setContainer($this->app);

        $this->assertSame('photo-1', $request->singlePhotoId());
        $this->assertSame(['67890@N01'], $request->contactNsids());
    }

    public function test_queue_photo_download_request_normalizes_flickr_photo_ids_string(): void
    {
        $request = QueuePhotoDownloadRequest::create('/download', 'POST', [
            'flickr_photo_ids' => 'photo-bulk-1',
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame(['photo-bulk-1'], $request->flickrPhotoIds());
    }

    public function test_queue_photo_upload_request_exposes_flickr_photo_ids(): void
    {
        $request = QueuePhotoUploadRequest::create('/upload', 'POST', [
            'flickr_photo_ids' => ['photo-a', 'photo-b', 'photo-a', ''],
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame(['photo-a', 'photo-b'], $request->flickrPhotoIds());
    }

    public function test_queue_photo_upload_request_exposes_storage_account_id_without_lookup(): void
    {
        $request = QueuePhotoUploadRequest::create('/upload', 'POST', [
            'storage_account_id' => '42',
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame(42, $request->storageAccountId());

        $defaultRequest = QueuePhotoUploadRequest::create('/upload', 'POST');
        $defaultRequest->setContainer($this->app);
        $defaultRequest->validateResolved();

        $this->assertNull($defaultRequest->storageAccountId());
    }

    public function test_begin_flickr_oauth_request_defaults_and_trims_app_profile(): void
    {
        $defaultRequest = BeginFlickrOAuthRequest::create('/flickr/oauth', 'GET');
        $defaultRequest->setContainer($this->app);
        $defaultRequest->validateResolved();

        $this->assertSame('main', $defaultRequest->appProfile());

        $customRequest = BeginFlickrOAuthRequest::create('/flickr/oauth', 'GET', [
            'app_profile' => ' archive ',
        ]);
        $customRequest->setContainer($this->app);
        $customRequest->validateResolved();

        $this->assertSame('archive', $customRequest->appProfile());
    }

    public function test_begin_storage_oauth_request_normalizes_route_and_query_values(): void
    {
        $request = BeginStorageOAuthRequest::create('/storage/oauth/google_photos', 'GET', [
            'account_id' => '12',
            'return_url' => '/storages/google-photos',
        ]);
        $request->setContainer($this->app);
        $this->bindRoute($request, '/storage/oauth/{provider}');

        $request->validateResolved();

        $this->assertSame(StorageDriver::GooglePhotos, $request->driver());
        $this->assertSame(12, $request->accountId());
        $this->assertSame('/storages/google-photos', $request->returnUrl());
    }

    public function test_storage_oauth_requests_discard_external_return_urls(): void
    {
        $beginRequest = BeginStorageOAuthRequest::create('/storage/oauth/google_photos', 'GET', [
            'return_url' => 'https://example.com/settings',
        ]);
        $beginRequest->setContainer($this->app);
        $this->bindRoute($beginRequest, '/storage/oauth/{provider}');
        $beginRequest->validateResolved();

        $this->assertNull($beginRequest->returnUrl());

        $reauthorizeRequest = ReauthorizeStorageRequest::create('/storage/reauthorize/1', 'GET', [
            'return_url' => '//example.com/settings',
        ]);
        $reauthorizeRequest->setContainer($this->app);
        $reauthorizeRequest->validateResolved();

        $this->assertNull($reauthorizeRequest->returnUrl());
    }

    public function test_storage_oauth_callback_request_validates_route_provider(): void
    {
        $request = StorageOAuthCallbackRequest::create('/storage/callback/google_photos', 'GET', [
            'code' => 'oauth-code',
        ]);
        $request->setContainer($this->app);
        $this->bindRoute($request, '/storage/callback/{provider}');

        $request->validateResolved();

        $this->assertSame('google_photos', $request->provider());
    }

    public function test_runtime_config_path_request_decodes_route_path(): void
    {
        $request = RuntimeConfigPathRequest::create('/settings/config/xflickr%2Eglobal_pause/reset', 'POST');
        $request->setContainer($this->app);
        $this->bindRoute($request, '/settings/config/{path}/reset');

        $request->validateResolved();

        $this->assertSame('xflickr.global_pause', $request->configPath());
    }

    private function bindRoute(Request $request, string $uri): void
    {
        $route = new Route('GET', $uri, []);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);
    }
}
