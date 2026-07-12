<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageApiLogger;

final class GooglePhotosDeleteService
{
    private const string API_BASE = 'https://photoslibrary.googleapis.com/v1';

    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(StorageAccount $account, array $credentials, array $itemIds, ?string $albumId): array
    {
        if ($albumId === null || $albumId === '') {
            throw new InvalidArgumentException(
                'Google Photos delete requires an album. Open an album and remove items from it.',
            );
        }

        $accessToken = $this->tokens->accessToken($credentials, $account);
        $deleted = [];
        $failed = [];

        foreach (array_chunk($itemIds, 50) as $chunk) {
            $endpoint = self::API_BASE.'/albums/'.urlencode($albumId).':batchRemoveMediaItems';
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->post($endpoint, [
                    'mediaItemIds' => $chunk,
                ]);
            $this->apiLogger->logRequest(
                'google_photos',
                'POST',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $account->id, 'item_count' => count($chunk)],
            );

            if ($response->successful()) {
                array_push($deleted, ...$chunk);

                continue;
            }

            $message = $this->errorMessage($response->json(), 'Google Photos remove from album failed.');

            foreach ($chunk as $itemId) {
                $failed[] = [
                    'id' => $itemId,
                    'message' => $message,
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function errorMessage(?array $payload, string $fallback): string
    {
        if ($payload === null) {
            return $fallback;
        }

        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
