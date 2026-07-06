<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StorageAccount;
use Illuminate\Support\Facades\Http;

final class OneDriveDeleteService
{
    private const string GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly StorageMicrosoftTokenService $tokens,
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
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->delete(self::GRAPH_BASE.'/me/drive/items/'.urlencode($itemId));

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
