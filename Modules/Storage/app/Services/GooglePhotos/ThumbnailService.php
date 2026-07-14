<?php

declare(strict_types=1);

namespace Modules\Storage\Services\GooglePhotos;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Repositories\StorageAccountRepository;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ThumbnailService
{
    public function __construct(
        private readonly GoogleTokenService $tokens,
        private readonly StorageAccountRepository $accounts,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    public function stream(int $accountId, string $mediaItemId): StreamedResponse
    {
        $account = $this->accounts->findById($accountId);

        if ($account === null || $account->provider !== 'google_photos') {
            throw new RuntimeException('Google Photos account not found.');
        }

        $credentials = $account->credentials ?? [];
        $accessToken = $this->tokens->accessToken($credentials, $account);

        $metadataEndpoint = "https://photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}";
        $startedAt = microtime(true);
        $metadata = Http::withToken($accessToken)
            ->get($metadataEndpoint);
        $this->apiLogger->logRequest(
            'google_photos',
            'GET',
            $metadataEndpoint,
            $startedAt,
            $metadata,
            null,
            ['account_id' => $accountId],
        );

        if (! $metadata->successful()) {
            throw new RuntimeException('Google Photos media item could not be loaded.');
        }

        $baseUrl = $metadata->json('baseUrl');
        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('Google Photos media item has no preview URL.');
        }

        if (! $this->isAllowedGooglePhotosBaseUrl($baseUrl)) {
            throw new RuntimeException('Invalid or unauthorized thumbnail domain prefix.');
        }

        $imageEndpoint = $baseUrl.'=w128-h128';
        $imageStartedAt = microtime(true);
        $image = Http::withToken($accessToken)
            ->withoutRedirecting()
            ->get($imageEndpoint);
        $this->apiLogger->logRequest(
            'google_photos',
            'GET',
            $imageEndpoint,
            $imageStartedAt,
            $image,
            null,
            ['account_id' => $accountId],
        );

        if (! $image->successful()) {
            throw new RuntimeException('Google Photos thumbnail could not be loaded.');
        }

        $contentType = $image->header('Content-Type') ?? 'image/jpeg';

        return response()->stream(function () use ($image): void {
            echo $image->body();
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function isAllowedGooglePhotosBaseUrl(string $baseUrl): bool
    {
        foreach ([
            'https://lh3.googleusercontent.com/',
            'https://lh4.googleusercontent.com/',
            'https://lh5.googleusercontent.com/',
            'https://lh6.googleusercontent.com/',
        ] as $prefix) {
            if (str_starts_with($baseUrl, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
