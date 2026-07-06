<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GooglePhotosUploadService
{
    private const string UPLOAD_URL = 'https://photoslibrary.googleapis.com/v1/uploads';

    private const string BATCH_CREATE_URL = 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate';

    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{id: string, path: string, etag: null, name: string, mime_type: string, thumbnail_url: string|null, modified_at: string}
     */
    public function uploadFile(StorageAccount $account, array $credentials, string $localPath, string $remotePath): array
    {
        $contents = file_get_contents($localPath);
        if ($contents === false) {
            throw new RuntimeException("Unable to read local file [{$localPath}].");
        }

        $accessToken = $this->tokens->accessToken($credentials, $account);
        $filename = basename($remotePath) !== '' ? basename($remotePath) : basename($localPath);

        $uploadResponse = Http::withToken($accessToken)
            ->timeout(120)
            ->withHeaders([
                'Content-Type' => 'application/octet-stream',
                'X-Goog-Upload-Protocol' => 'raw',
            ])
            ->withBody($contents, 'application/octet-stream')
            ->post(self::UPLOAD_URL);

        if (! $uploadResponse->successful()) {
            throw new RuntimeException('Google Photos upload failed: HTTP '.$uploadResponse->status());
        }

        $uploadToken = trim($uploadResponse->body());
        if ($uploadToken === '') {
            throw new RuntimeException('Google Photos upload token was empty.');
        }

        $createResponse = Http::withToken($accessToken)
            ->timeout(60)
            ->post(self::BATCH_CREATE_URL, [
                'newMediaItems' => [[
                    'description' => $filename,
                    'simpleMediaItem' => [
                        'fileName' => $filename,
                        'uploadToken' => $uploadToken,
                    ],
                ]],
            ]);

        if (! $createResponse->successful()) {
            throw new RuntimeException($this->apiError($createResponse->json(), 'Google Photos media item creation failed.'));
        }

        $payload = $createResponse->json();
        $results = is_array($payload) ? ($payload['newMediaItemResults'][0] ?? null) : null;
        $mediaItem = is_array($results) ? ($results['mediaItem'] ?? null) : null;
        $mediaId = is_array($mediaItem) ? (string) ($mediaItem['id'] ?? '') : '';

        if ($mediaId === '') {
            $status = is_array($results) ? ($results['status'] ?? null) : null;
            $message = is_array($status) ? (string) ($status['message'] ?? 'Unknown Google Photos error.') : 'Unknown Google Photos error.';
            throw new RuntimeException($message);
        }

        $baseUrl = is_array($mediaItem) ? (string) ($mediaItem['baseUrl'] ?? '') : '';
        $mimeType = is_array($mediaItem) ? (string) ($mediaItem['mimeType'] ?? 'image/jpeg') : 'image/jpeg';
        $creationTime = is_array($mediaItem['mediaMetadata'] ?? null)
            ? (string) ($mediaItem['mediaMetadata']['creationTime'] ?? '')
            : '';

        return [
            'id' => $mediaId,
            'path' => $mediaId,
            'etag' => null,
            'name' => $filename,
            'mime_type' => $mimeType !== '' ? $mimeType : 'image/jpeg',
            'thumbnail_url' => $baseUrl !== '' ? $baseUrl.'=w200-h200' : null,
            'modified_at' => $creationTime !== '' ? $creationTime : now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function apiError(?array $payload, string $fallback): string
    {
        if ($payload === null) {
            return $fallback;
        }

        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
