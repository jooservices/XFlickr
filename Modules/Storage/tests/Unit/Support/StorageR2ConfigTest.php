<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Support;

use Modules\Storage\Support\StorageR2Config;
use PHPUnit\Framework\TestCase;

final class StorageR2ConfigTest extends TestCase
{
    public function test_it_builds_object_keys_with_optional_prefix(): void
    {
        $config = StorageR2Config::from([
            'access_key_id' => 'key',
            'secret_access_key' => 'secret',
            'bucket' => 'photos',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'prefix' => 'xflickr',
        ]);

        $this->assertSame('xflickr/Flickr/nsid/photo.jpg', $config->objectKey('Flickr/nsid/photo.jpg'));
        $this->assertSame('Flickr/nsid/photo.jpg', $config->relativePath('xflickr/Flickr/nsid/photo.jpg'));
        $this->assertSame('xflickr/Flickr/nsid/', $config->listPrefix('Flickr/nsid'));
    }

    public function test_it_normalizes_prefix_and_defaults_region(): void
    {
        $config = StorageR2Config::from([
            'access_key_id' => 'key',
            'secret_access_key' => 'secret',
            'bucket' => 'photos',
            'endpoint' => 'https://example.r2.cloudflarestorage.com',
            'prefix' => '/uploads/',
        ]);

        $this->assertSame('uploads', $config->prefix);
        $this->assertSame('auto', $config->region);
    }
}
