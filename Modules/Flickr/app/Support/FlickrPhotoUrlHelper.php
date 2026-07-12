<?php

declare(strict_types=1);

namespace Modules\Flickr\Support;

final class FlickrPhotoUrlHelper
{
    /** @var list<string> */
    private const PREFERRED_GET_SIZES_LABELS = [
        'original',
        'x-large 6k',
        'x-large 5k',
        'x-large 4k',
        'x-large 3k',
        'x-large',
        'large',
        'large 1600',
        'large 1024',
        'medium',
        'medium 800',
        'medium 640',
        'small',
        'square',
        'thumbnail',
    ];

    /**
     * @return array{
     *     ok: bool,
     *     message: string|null,
     *     raw_sizes: list<array<string, mixed>>,
     *     candidates: list<array{url: string, variant: string}>
     * }
     */
    public static function fetchSizesFromApi(object $photosApi, string $photoId): array
    {
        if (! is_object($photosApi) || ! is_callable([$photosApi, 'getSizes'])) {
            return [
                'ok' => false,
                'message' => 'Flickr photos API does not support getSizes.',
                'raw_sizes' => [],
                'candidates' => [],
            ];
        }

        $response = $photosApi->getSizes($photoId);
        if (! $response->ok) {
            return [
                'ok' => false,
                'message' => (string) ($response->message ?? $response->stat ?? 'flickr.photos.getSizes failed'),
                'raw_sizes' => [],
                'candidates' => [],
            ];
        }

        $rawSizes = self::normalizeGetSizesList($response->data['sizes']['size'] ?? []);
        $candidates = self::candidatesFromGetSizesList($rawSizes);

        return [
            'ok' => true,
            'message' => null,
            'raw_sizes' => $rawSizes,
            'candidates' => $candidates,
        ];
    }

    /**
     * @return list<array{url: string, variant: string}>
     */
    public static function allDownloadsFromGetSizes(object $photosApi, string $photoId): array
    {
        return self::fetchSizesFromApi($photosApi, $photoId)['candidates'];
    }

    /**
     * @param  list<array<string, mixed>>  $rawSizes
     * @return array{url: string, variant: string}|null
     */
    public static function bestCandidateFromGetSizesList(array $rawSizes): ?array
    {
        return self::candidatesFromGetSizesList($rawSizes)[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function normalizeGetSizesList(mixed $sizes): array
    {
        if (! is_array($sizes) || $sizes === []) {
            return [];
        }

        if (isset($sizes['label'])) {
            return [$sizes];
        }

        return array_values(array_filter($sizes, is_array(...)));
    }

    /**
     * @param  list<array<string, mixed>>  $rawSizes
     * @return list<array{url: string, variant: string}>
     */
    public static function candidatesFromGetSizesList(array $rawSizes): array
    {
        $byLabel = [];

        foreach ($rawSizes as $size) {
            $label = strtolower(trim((string) ($size['label'] ?? '')));
            $source = (string) ($size['source'] ?? '');
            if ($label !== '' && $source !== '') {
                $byLabel[$label] = $source;
            }
        }

        $candidates = [];
        $seen = [];

        foreach (self::PREFERRED_GET_SIZES_LABELS as $label) {
            if (! isset($byLabel[$label]) || isset($seen[$byLabel[$label]])) {
                continue;
            }

            $seen[$byLabel[$label]] = true;
            $candidates[] = ['url' => $byLabel[$label], 'variant' => $label];
        }

        foreach ($byLabel as $label => $source) {
            if (isset($seen[$source])) {
                continue;
            }

            $seen[$source] = true;
            $candidates[] = ['url' => $source, 'variant' => $label];
        }

        return $candidates;
    }

    public static function staticUrl(
        string $photoId,
        ?string $secret,
        ?string $server,
        string $size = 'q',
    ): ?string {
        if ($photoId === '' || $secret === null || $secret === '' || $server === null || $server === '') {
            return null;
        }

        return sprintf(
            'https://live.staticflickr.com/%s/%s_%s_%s.jpg',
            $server,
            $photoId,
            $secret,
            $size,
        );
    }

    public static function extensionFromUrl(string $url): ?string
    {
        return self::extensionFromPath($url);
    }

    public static function extensionFromFormat(?string $format): ?string
    {
        if ($format === null || trim($format) === '') {
            return null;
        }

        return match (strtolower(trim($format))) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            'mp4' => 'mp4',
            'mov' => 'mov',
            default => strtolower(trim($format)),
        };
    }

    public static function resolveExtension(string $urlOrPath, ?string $format = null): string
    {
        return self::extensionFromPath($urlOrPath)
            ?? self::extensionFromFormat($format)
            ?? 'jpg';
    }

    public static function originalNameFor(string $flickrPhotoId, string $extension): string
    {
        return "{$flickrPhotoId}_original.{$extension}";
    }

    private static function extensionFromPath(string $pathOrUrl): ?string
    {
        $path = parse_url($pathOrUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = $pathOrUrl;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '' || ! preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            return null;
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }
}
