<?php

declare(strict_types=1);

namespace Modules\Storage\Services\OneDrive;

use Illuminate\Support\Facades\Http;
use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\MicrosoftTokenService;
use Modules\Storage\Support\StorageApiLogger;

final class DeleteService implements StorageDeleteDriver
{
    private const string GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly MicrosoftTokenService $tokens,
        private readonly StorageApiLogger $apiLogger,
    ) {}

    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array
    {
        $credentials = $account->credentials ?? [];
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
