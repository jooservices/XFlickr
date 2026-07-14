<?php

declare(strict_types=1);

namespace Modules\Storage\Services\R2;

use Modules\Storage\Contracts\StorageDeleteDriver;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Support\StorageR2Config;
use Throwable;

final class DeleteService implements StorageDeleteDriver
{
    public function __construct(
        private readonly StorageFlysystemFactory $flysystem,
    ) {}

    public function deleteMany(StorageAccount $account, array $itemIds, ?string $containerId = null): array
    {
        $credentials = $account->credentials ?? [];
        $config = StorageR2Config::from($credentials);
        $disk = $this->flysystem->diskForAccount($account);

        $deleted = [];
        $failed = [];

        foreach ($itemIds as $itemId) {
            $objectKey = $config->objectKey($itemId);

            try {
                if ($disk->exists($objectKey)) {
                    $disk->delete($objectKey);
                }

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
