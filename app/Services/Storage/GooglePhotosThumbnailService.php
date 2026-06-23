<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Repositories\StorageAccountRepository;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GooglePhotosThumbnailService
{
    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
        private readonly StorageAccountRepository $accounts,
    ) {}

    public function stream(int $accountId, string $mediaItemId): StreamedResponse
    {
        $account = $this->accounts->findById($accountId);

        if ($account === null || $account->provider !== 'google_photos') {
            throw new RuntimeException('Google Photos account not found.');
        }

        $credentials = $account->credentials ?? [];
        $accessToken = $this->tokens->accessToken($credentials);

        $metadata = Http::withToken($accessToken)
            ->get("https://photoslibrary.googleapis.com/v1/mediaItems/{$mediaItemId}");

        if (! $metadata->successful()) {
            throw new RuntimeException('Google Photos media item could not be loaded.');
        }

        $baseUrl = $metadata->json('baseUrl');
        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('Google Photos media item has no preview URL.');
        }

        $image = Http::withToken($accessToken)->get($baseUrl.'=w128-h128');

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
}
