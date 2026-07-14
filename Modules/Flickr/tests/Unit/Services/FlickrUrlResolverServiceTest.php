<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Flickr\Exceptions\FlickrUrlResolutionException;
use Modules\Flickr\Services\FlickrUrlResolverService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;

final class FlickrUrlResolverServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_extract_photo_id_from_photo_page_url(): void
    {
        $service = app(FlickrUrlResolverService::class);

        $this->assertSame(
            '52957706290',
            $service->extractPhotoId('https://www.flickr.com/photos/someone/52957706290/in/dateposted/'),
        );
        $this->assertNull($service->extractPhotoId('https://www.flickr.com/photos/someone/'));
    }

    public function test_normalize_url_rejects_non_flickr_hosts(): void
    {
        $service = app(FlickrUrlResolverService::class);

        $this->expectException(FlickrUrlResolutionException::class);
        $service->normalizeUrl('https://example.com/people/x');
    }

    public function test_resolve_contact_row_via_lookup_user(): void
    {
        $connection = $this->createFlickrConnection();
        $nsid = FlickrNsid::fake();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()
                ->pushJson([
                    'stat' => 'ok',
                    'user' => ['id' => $nsid],
                ])
                ->pushJson([
                    'stat' => 'ok',
                    'person' => [
                        'username' => ['_content' => 'resolved-user'],
                        'realname' => ['_content' => 'Resolved User'],
                    ],
                ]),
        );

        $row = app(FlickrUrlResolverService::class)->resolveContactRow(
            $connection,
            'https://www.flickr.com/photos/resolved-user/',
        );

        $this->assertSame($nsid, $row['nsid']);
        $this->assertSame('resolved-user', $row['username']);
        $this->assertSame('Resolved User', $row['realname']);
    }

    public function test_resolve_contact_row_via_photo_get_info(): void
    {
        $connection = $this->createFlickrConnection();
        $nsid = FlickrNsid::fake();

        $this->bindFakeFlickrTransport(
            FakeFlickrTransport::new()
                ->pushJson([
                    'stat' => 'ok',
                    'photo' => [
                        'id' => '52957706290',
                        'owner' => ['nsid' => $nsid],
                    ],
                ])
                ->pushJson([
                    'stat' => 'ok',
                    'person' => [
                        'username' => ['_content' => 'photo-owner'],
                        'realname' => ['_content' => 'Photo Owner'],
                    ],
                ]),
        );

        $row = app(FlickrUrlResolverService::class)->resolveContactRow(
            $connection,
            'https://www.flickr.com/photos/alias/52957706290/',
        );

        $this->assertSame($nsid, $row['nsid']);
        $this->assertSame('photo-owner', $row['username']);
    }
}
