<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Support;

use Modules\Flickr\Support\FlickrPhotoUrlHelper;
use Modules\Flickr\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

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
            'path without extension' => ['flickr/nsid/photos/photo-1_secret', null],
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
            'jpg fallback' => ['https://example.test/no-extension', null, 'jpg'],
        ];
    }

    #[DataProvider('extensionFromFormatProvider')]
    public function test_extension_from_format(?string $format, ?string $expected): void
    {
        $this->assertSame($expected, FlickrPhotoUrlHelper::extensionFromFormat($format));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function extensionFromFormatProvider(): array
    {
        return [
            'jpeg normalizes' => ['JPEG', 'jpg'],
            'webp passthrough' => ['webp', 'webp'],
            'empty string' => ['', null],
            'null' => [null, null],
            'unknown format lowercases' => ['HEIC', 'heic'],
        ];
    }

    public function test_normalize_get_sizes_list_handles_single_and_multiple_entries(): void
    {
        $single = FlickrPhotoUrlHelper::normalizeGetSizesList([
            'label' => 'Large',
            'source' => 'https://example.test/large.jpg',
        ]);

        $this->assertSame([
            ['label' => 'Large', 'source' => 'https://example.test/large.jpg'],
        ], $single);

        $multiple = FlickrPhotoUrlHelper::normalizeGetSizesList([
            ['label' => 'Small', 'source' => 'https://example.test/small.jpg'],
            ['label' => 'Original', 'source' => 'https://example.test/original.jpg'],
        ]);

        $this->assertCount(2, $multiple);
    }

    public function test_candidates_from_get_sizes_list_prefers_largest_known_label(): void
    {
        $candidates = FlickrPhotoUrlHelper::candidatesFromGetSizesList([
            ['label' => 'Medium', 'source' => 'https://example.test/medium.jpg'],
            ['label' => 'Original', 'source' => 'https://example.test/original.jpg'],
            ['label' => 'custom-size', 'source' => 'https://example.test/custom.jpg'],
        ]);

        $this->assertSame('https://example.test/original.jpg', $candidates[0]['url']);
        $this->assertSame('original', $candidates[0]['variant']);
        $this->assertSame('https://example.test/medium.jpg', $candidates[1]['url']);
        $this->assertSame('custom-size', $candidates[2]['variant']);
    }

    public function test_best_candidate_from_get_sizes_list_returns_first_preferred_size(): void
    {
        $best = FlickrPhotoUrlHelper::bestCandidateFromGetSizesList([
            ['label' => 'Square', 'source' => 'https://example.test/square.jpg'],
            ['label' => 'Large', 'source' => 'https://example.test/large.jpg'],
        ]);

        $this->assertSame('https://example.test/large.jpg', $best['url'] ?? null);
        $this->assertSame('large', $best['variant'] ?? null);
    }

    public function test_fetch_sizes_from_api_rejects_invalid_response_shape(): void
    {
        $result = FlickrPhotoUrlHelper::fetchSizesFromApi(
            static fn (string $photoId): object => new stdClass,
            'photo-1',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'Flickr photos API returned an invalid getSizes response.',
            $result['message'],
        );
        $this->assertSame([], $result['candidates']);
    }

    public function test_fetch_sizes_from_api_surfaces_flickr_error_message(): void
    {
        $result = FlickrPhotoUrlHelper::fetchSizesFromApi(
            static fn (string $photoId): object => (object) [
                'ok' => false,
                'error' => (object) ['code' => 1, 'message' => 'Photo not found'],
                'data' => [],
            ],
            'missing-photo',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('code 1: Photo not found', $result['message']);
    }

    public function test_fetch_sizes_from_api_returns_candidates_on_success(): void
    {
        $result = FlickrPhotoUrlHelper::fetchSizesFromApi(
            static fn (string $photoId): object => (object) [
                'ok' => true,
                'data' => [
                    'sizes' => [
                        'size' => [
                            ['label' => 'Original', 'source' => 'https://example.test/original.jpg'],
                        ],
                    ],
                ],
            ],
            'photo-1',
        );

        $this->assertTrue($result['ok']);
        $this->assertNull($result['message']);
        $this->assertSame('https://example.test/original.jpg', $result['candidates'][0]['url']);
    }

    public function test_all_downloads_from_get_sizes_delegates_to_fetch_sizes_from_api(): void
    {
        $downloads = FlickrPhotoUrlHelper::allDownloadsFromGetSizes(
            static fn (string $photoId): object => (object) [
                'ok' => true,
                'data' => [
                    'sizes' => [
                        'size' => [
                            ['label' => 'Large', 'source' => 'https://example.test/large.jpg'],
                        ],
                    ],
                ],
            ],
            'photo-1',
        );

        $this->assertSame('https://example.test/large.jpg', $downloads[0]['url']);
    }

    public function test_static_url_returns_null_for_incomplete_metadata(): void
    {
        $this->assertNull(FlickrPhotoUrlHelper::staticUrl('', 'secret', '65535'));
        $this->assertNull(FlickrPhotoUrlHelper::staticUrl('photo-1', null, '65535'));
        $this->assertNull(FlickrPhotoUrlHelper::staticUrl('photo-1', 'secret', null));
    }

    public function test_static_url_builds_live_staticflickr_url(): void
    {
        $this->assertSame(
            'https://live.staticflickr.com/65535/photo-1_abc123_q.jpg',
            FlickrPhotoUrlHelper::staticUrl('photo-1', 'abc123', '65535'),
        );
    }

    public function test_original_name_for(): void
    {
        $this->assertSame('photo-1_original.png', FlickrPhotoUrlHelper::originalNameFor('photo-1', 'png'));
    }
}
