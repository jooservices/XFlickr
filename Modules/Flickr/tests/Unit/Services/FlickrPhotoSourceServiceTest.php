<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use JOOservices\Flickr\Client\FakeFlickrTransport;
use Modules\Crawler\Models\Photo;
use Modules\Crawler\Services\FlickrClientFactory;
use Modules\Flickr\Services\FlickrPhotoSourceService;
use Modules\Flickr\Tests\TestCase;
use RuntimeException;
use Tests\Support\CreatesFlickrConnection;

final class FlickrPhotoSourceServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_resolve_throws_when_connection_not_found(): void
    {
        $resolver = app(FlickrPhotoSourceService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection not found for key: missing@N01');

        $resolver->resolveDownload('photo-123', 'missing@N01');
    }

    public function test_resolve_returns_resolved_source_for_flickr_photo(): void
    {
        $connection = $this->createFlickrConnection();
        $sourceId = '52957706290';

        Photo::query()->create([
            'flickr_photo_id' => $sourceId,
            'owner_nsid' => $connection->connection_key,
            'title' => 'Test Photo',
            'secret' => 'abc123',
            'server' => '65535',
            'raw_payload' => [
                'sizes' => [
                    ['label' => 'Original', 'source' => "https://live.staticflickr.com/65535/{$sourceId}_abc_o.jpg", 'width' => 4000, 'height' => 3000],
                ],
            ],
        ]);

        $transport = new FakeFlickrTransport;
        $this->app->instance(FlickrClientFactory::class, new FlickrClientFactory($transport));

        $resolver = app(FlickrPhotoSourceService::class);
        $result = $resolver->resolveDownload($sourceId, $connection->connection_key);

        $this->assertNotEmpty($result->url);
        $this->assertNotEmpty($result->destinationPath);
        $this->assertNotEmpty($result->originalName);
        $this->assertSame($sourceId, $result->metadata['flickr_photo_id']);
        $this->assertSame($connection->connection_key, $result->metadata['connection_key']);
    }

    public function test_remote_upload_path_returns_expected_path(): void
    {
        $resolver = app(FlickrPhotoSourceService::class);
        $result = $resolver->remoteUploadPath(
            '98765432101@N01',
            '52957706290',
            'downloads/52957706290_original.jpg',
        );

        $this->assertStringStartsWith('Flickr/98765432101@N01/Photos/', $result);
        $this->assertStringContainsString('52957706290', $result);
    }
}
