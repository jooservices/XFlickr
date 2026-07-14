<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageApiLogger;
use RuntimeException;

final class GooglePhotosUploadService
{
    private const string UPLOAD_URL = 'https://photoslibrary.googleapis.com/v1/uploads';

    private const string BATCH_CREATE_URL = 'https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate';

    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{id: string, path: string, etag: null, name: string, mime_type: string, thumbnail_url: string|null, modified_at: string}
     */
    public function uploadFile(StorageAccount $account, array $credentials, string $localPath, string $remotePath): array
    {
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to read local file [{$localPath}].");
        }

        $accessToken = $this->tokens->accessToken($credentials, $account);
        $filename = basename($remotePath) !== '' ? basename($remotePath) : basename($localPath);
        $body = Utils::streamFor($stream);

        try {
            $startedAt = microtime(true);
            $uploadResponse = Http::withToken($accessToken)
                ->timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/octet-stream',
                    'X-Goog-Upload-Protocol' => 'raw',
                ])
                ->withBody($body, 'application/octet-stream')
                ->post(self::UPLOAD_URL);
            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                self::UPLOAD_URL,
                $startedAt,
                $uploadResponse,
                null,
                ['account_id' => $account->id, 'filename' => $filename],
            );
        } finally {
            $body->close();
        }

        if (! $uploadResponse->successful()) {
            throw new RuntimeException('Google Photos upload failed: HTTP '.$uploadResponse->status());
        }

        $uploadToken = trim($uploadResponse->body());
        if ($uploadToken === '') {
            throw new RuntimeException('Google Photos upload token was empty.');
        }

        $startedAt = microtime(true);
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
        $this->apiLogger->logRequest(
            'google_photos',
            'POST',
            self::BATCH_CREATE_URL,
            $startedAt,
            $createResponse,
            null,
            [
                'account_id' => $account->id,
                'filename' => $filename,
                'upload_token_present' => true,
            ],
        );

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
