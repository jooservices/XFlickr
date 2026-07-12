<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageApiLogger;

final class OneDriveDeleteService
{
    private const string GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly StorageMicrosoftTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(StorageAccount $account, array $credentials, array $itemIds): array
    {
        $accessToken = $this->tokens->accessToken($credentials, $account);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            $endpoint = self::GRAPH_BASE.'/me/drive/items/'.urlencode($itemId);
            $startedAt = microtime(true);
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->delete($endpoint);
            $this->apiLogger->logRequest(
                'onedrive',
                'DELETE',
                $endpoint,
                $startedAt,
                $response,
                null,
                ['account_id' => $account->id, 'item_id' => $itemId],
            );

            if ($response->successful() || $response->status() === 404) {
                $deleted[] = $itemId;

                continue;
            }

            $message = $response->json('error.message');

            $failed[] = [
                'id' => $itemId,
                'message' => is_string($message) && $message !== '' ? $message : 'OneDrive delete failed.',
            ];
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}
