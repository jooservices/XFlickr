<?php

declare(strict_types=1);

namespace Modules\Storage\Services\GoogleDrive;

use Google\Service\Drive;
use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\Tokens\GoogleTokenService;
use Throwable;

final class DeleteService implements StorageDeleteDriver
{
    public function __construct(
        private readonly GoogleTokenService $tokens,
    ) {}

    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array
    {
        $credentials = $account->credentials ?? [];
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
