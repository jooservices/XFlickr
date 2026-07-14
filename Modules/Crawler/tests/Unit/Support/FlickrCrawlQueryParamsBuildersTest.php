<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Support;

use JOOservices\Flickr\Auth\InMemoryTokenStore;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\Flickr\Config\FlickrConfig;
use JOOservices\Flickr\DTO\Auth\AccessTokenData;
use JOOservices\Flickr\FlickrFactory;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Support\FlickrCrawlQueryParams;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class FlickrCrawlQueryParamsBuildersTest extends TestCase
{
    public function test_people_photos_includes_extras_and_pagination(): void
    {
        $nsid = FlickrNsid::fake();

        $query = FlickrCrawlQueryParams::peoplePhotos($nsid, 2, 50);

        $this->assertSame($nsid, $query['user_id']);
        $this->assertSame(2, $query['page']);
        $this->assertSame(50, $query['per_page']);
        $this->assertSame(FlickrCrawlQueryParams::PHOTO_EXTRAS, $query['extras']);
        $this->assertArrayNotHasKey('safe_search', $query);
    }

    public function test_photosets_list_and_photoset_photos_builders(): void
    {
        $nsid = FlickrNsid::fake();
        $photosetId = (string) fake()->numerify('########');

        $list = FlickrCrawlQueryParams::photosetsList($nsid, 1, 100);
        $photos = FlickrCrawlQueryParams::photosetPhotos($photosetId, 3, 25);

        $this->assertSame($nsid, $list['user_id']);
        $this->assertSame(1, $list['page']);
        $this->assertArrayNotHasKey('extras', $list);

        $this->assertSame($photosetId, $photos['photoset_id']);
        $this->assertSame(3, $photos['page']);
        $this->assertSame(FlickrCrawlQueryParams::PHOTO_EXTRAS, $photos['extras']);
    }

    public function test_galleries_and_favorites_builders(): void
    {
        $nsid = FlickrNsid::fake();
        $galleryId = (string) fake()->numerify('########');

        $galleries = FlickrCrawlQueryParams::galleriesList($nsid, 1, 10);
        $galleryPhotos = FlickrCrawlQueryParams::galleryPhotos($galleryId, 2, 40);
        $favorites = FlickrCrawlQueryParams::favoritesList($nsid, 4, 30);

        $this->assertSame($nsid, $galleries['user_id']);
        $this->assertSame($galleryId, $galleryPhotos['gallery_id']);
        $this->assertSame(FlickrCrawlQueryParams::PHOTO_EXTRAS, $galleryPhotos['extras']);
        $this->assertSame(4, $favorites['page']);
        $this->assertSame(30, $favorites['per_page']);
    }

    public function test_visibility_filters_merge_into_builders_when_configured(): void
    {
        RuntimeConfig::set('xflickr_crawl.safe_search', 1, 'int');
        RuntimeConfig::set('xflickr_crawl.privacy_filter', 5, 'int');
        RuntimeConfig::refresh();

        $query = FlickrCrawlQueryParams::photosetsList(FlickrNsid::fake(), 1, 10);

        $this->assertSame(1, $query['safe_search']);
        $this->assertSame(5, $query['privacy_filter']);
    }

    public function test_call_authenticated_passes_request_options(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson(['stat' => 'ok', 'method' => 'echo']);
        $client = FlickrFactory::make(
            FlickrConfig::from(['apiKey' => 'key', 'apiSecret' => 'secret']),
            tokenStore: new InMemoryTokenStore(AccessTokenData::from([
                'oauthToken' => 'tok',
                'oauthTokenSecret' => 'sec',
                'userNsid' => FlickrNsid::fake(),
            ])),
            transport: $transport,
        );

        $response = FlickrCrawlQueryParams::call(
            $client,
            'flickr.test.echo',
            ['foo' => 'bar'],
            authenticated: true,
        );

        $this->assertTrue($response->ok);
        $this->assertNotEmpty($transport->sentRequests());
        $transport->assertSentMethod('flickr.test.echo');
    }

    public function test_call_unauthenticated_omits_auth_option(): void
    {
        $transport = FakeFlickrTransport::new()->pushJson(['stat' => 'ok']);
        $client = FlickrFactory::make(
            FlickrConfig::from(['apiKey' => 'key', 'apiSecret' => 'secret']),
            transport: $transport,
        );

        $response = FlickrCrawlQueryParams::call(
            $client,
            'flickr.test.echo',
            ['foo' => 'bar'],
            authenticated: false,
        );

        $this->assertTrue($response->ok);
        $transport->assertSentMethod('flickr.test.echo');
    }

    public function test_zero_visibility_filters_are_omitted_as_weird_edge(): void
    {
        RuntimeConfig::set('xflickr_crawl.safe_search', 0, 'int');
        RuntimeConfig::set('xflickr_crawl.privacy_filter', 0, 'int');
        RuntimeConfig::refresh();

        $filters = FlickrCrawlQueryParams::visibilityFilters();

        $this->assertSame([], $filters);
    }

    public function test_php_config_fallback_when_runtime_keys_absent(): void
    {
        RuntimeConfig::forget('xflickr_crawl.safe_search');
        RuntimeConfig::forget('xflickr_crawl.privacy_filter');
        RuntimeConfig::forget('xflickr_crawl.people_photos_safe_search');
        RuntimeConfig::refresh();

        config([
            'xflickr-crawler.crawl.safe_search' => 2,
            'xflickr-crawler.crawl.privacy_filter' => 3,
        ]);

        $filters = FlickrCrawlQueryParams::visibilityFilters();

        $this->assertSame(2, $filters['safe_search']);
        $this->assertSame(3, $filters['privacy_filter']);
    }
}
