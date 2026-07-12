<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Google\Service\Drive;
use Modules\Storage\Models\StorageAccount;
use Throwable;

final class GoogleDriveDeleteService
{
    public function __construct(
        private readonly StorageGoogleTokenService $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(StorageAccount $account, array $credentials, array $itemIds): array
    {
        $client = $this->tokens->clientForAccount($credentials, $account);
        $service = new Drive($client);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            try {
                $service->files->delete($itemId);
                $deleted[] = $itemId;
            } catch (Throwable $e) {
                $failed[] = [
                    'id' => $itemId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}
