<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FlickrPhotoUrlHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FlickrPhotoUrlHelperTest extends TestCase
{
    #[DataProvider('urlExtensionProvider')]
    public function test_extension_from_url(string $url, ?string $expected): void
    {
        $this->assertSame($expected, FlickrPhotoUrlHelper::extensionFromUrl($url));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function urlExtensionProvider(): array
    {
        return [
            'jpg url' => ['https://example.test/photo-1.jpg', 'jpg'],
            'jpeg url' => ['https://example.test/photo-1.jpeg', 'jpg'],
            'png url' => ['https://example.test/original.png', 'png'],
            'gif url' => ['https://example.test/photo.gif', 'gif'],
            'mp4 url' => ['https://example.test/video.mp4', 'mp4'],
            'path without extension' => ['flickr/nsid/photos/photo-1_secret', null],
            'url without extension' => ['https://live.staticflickr.com/65535/photo-1_abc_o', null],
        ];
    }

    #[DataProvider('resolveExtensionProvider')]
    public function test_resolve_extension(string $urlOrPath, ?string $format, string $expected): void
    {
        $this->assertSame($expected, FlickrPhotoUrlHelper::resolveExtension($urlOrPath, $format));
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function resolveExtensionProvider(): array
    {
        return [
            'url wins over format' => ['https://example.test/original.png', 'jpg', 'png'],
            'format fallback' => ['https://example.test/no-extension', 'png', 'png'],
            'jpeg format normalizes to jpg' => ['https://example.test/no-extension', 'jpeg', 'jpg'],
            'jpg fallback' => ['https://example.test/no-extension', null, 'jpg'],
            'local path' => ['flickr/friend@N01/photos/photo-1_abc.png', null, 'png'],
        ];
    }

    public function test_original_name_for(): void
    {
        $this->assertSame('photo-1_original.png', FlickrPhotoUrlHelper::originalNameFor('photo-1', 'png'));
    }
}
