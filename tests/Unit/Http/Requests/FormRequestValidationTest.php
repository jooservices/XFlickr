<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Flickr\CrawlFlickrAccountRequest;
use App\Http\Requests\Settings\StoreFlickrAppProfileRequest;
use App\Http\Requests\Transfer\QueuePhotoDownloadRequest;
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
}
