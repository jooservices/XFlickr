<?php

declare(strict_types=1);

namespace Modules\Storage\Services;

use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Support\StorageR2Config;
use Throwable;

final class R2DeleteService
{
    public function __construct(
        private readonly StorageFlysystemFactory $flysystem,
    ) {}

    /**
     * @param  list<string>  $itemIds
     * @return array{deleted: list<string>, failed: list<array{id: string, message: string}>}
     */
    public function deleteMany(StorageAccount $account, array $itemIds): array
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
