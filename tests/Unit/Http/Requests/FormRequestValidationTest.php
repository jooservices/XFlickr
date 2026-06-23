<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\StorageDriver;
use App\Http\Requests\Flickr\BeginFlickrOAuthRequest;
use App\Http\Requests\Flickr\CrawlFlickrAccountRequest;
use App\Http\Requests\Settings\StoreFlickrAppProfileRequest;
use App\Http\Requests\Storage\BeginStorageOAuthRequest;
use App\Http\Requests\Storage\ReauthorizeStorageRequest;
use App\Http\Requests\Storage\StorageOAuthCallbackRequest;
use App\Http\Requests\Transfer\QueuePhotoDownloadRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
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
        $request = CrawlFlickrAccountRequest::create('/crawl', 'POST', ['types' => 'photos']);
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

    private function bindRoute(Request $request, string $uri): void
    {
        $route = new Route('GET', $uri, []);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);
    }
}
