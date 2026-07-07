<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Flickr\FlickrPhotoSizeResolver;
use JOOservices\XFlickrCrawler\Models\Photo;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class FlickrPhotoSizeResolverTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_it_uses_cached_get_sizes_without_calling_flickr_api(): void
    {
        $connection = $this->createFlickrConnection(['connection_key' => 'me@N01']);

        Photo::query()->create([
            'flickr_photo_id' => 'photo-1',
            'owner_nsid' => 'friend@N01',
            'title' => 'Test',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Original', 'source' => 'https://example.test/original.jpg'],
                ],
            ],
        ]);

        $download = app(FlickrPhotoSizeResolver::class)->resolve('photo-1', $connection);

        $this->assertSame('https://example.test/original.jpg', $download->url);
        $this->assertSame('original', $download->variant);
    }
}
