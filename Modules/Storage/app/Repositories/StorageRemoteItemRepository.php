<?php

declare(strict_types=1);

namespace Modules\Storage\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Storage\Models\StorageRemoteItem;

/**
 * @extends EloquentRepository<StorageRemoteItem>
 */
final class StorageRemoteItemRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StorageRemoteItem $model)
    {
        parent::__construct($model);
    }

    /**
     * @return LengthAwarePaginator<int, StorageRemoteItem>
     */
    public function paginateForParent(int $accountId, string $parentRemoteId, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->orderByDesc('modified_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'item_page', max(1, $page));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByRemoteId(int $accountId, string $remoteId, array $attributes): StorageRemoteItem
    {
        return $this->newQuery()->updateOrCreate(
            [
                'storage_account_id' => $accountId,
                'remote_id' => $remoteId,
            ],
            $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function upsertFromBrowseItem(int $accountId, string $parentRemoteId, array $item): StorageRemoteItem
    {
        return $this->newQuery()->updateOrCreate(
            [
                'storage_account_id' => $accountId,
                'remote_id' => (string) ($item['id'] ?? ''),
            ],
            [
                'parent_remote_id' => $parentRemoteId,
                'name' => (string) ($item['name'] ?? 'Untitled'),
                'mime_type' => isset($item['mime_type']) ? (string) $item['mime_type'] : null,
                'thumbnail_url' => isset($item['thumbnail_url']) ? (string) $item['thumbnail_url'] : null,
                'size' => isset($item['size']) && is_numeric($item['size']) ? (int) $item['size'] : null,
                'modified_at' => isset($item['modified_at']) ? Carbon::parse((string) $item['modified_at']) : now(),
                'web_url' => isset($item['web_url']) ? (string) $item['web_url'] : null,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * @return list<string>
     */
    public function listRemoteIdsForParent(int $accountId, string $parentRemoteId): array
    {
        return $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->pluck('remote_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  list<string>  $remoteIds
     */
    public function deleteByRemoteIds(int $accountId, array $remoteIds, ?string $parentRemoteId = null): void
    {
        if ($remoteIds === []) {
            return;
        }

        $query = $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->whereIn('remote_id', $remoteIds);

        if ($parentRemoteId !== null) {
            $query->where('parent_remote_id', $parentRemoteId);
        }

        $query->delete();
    }

    public function deleteAllForParent(int $accountId, string $parentRemoteId): void
    {
        $this->newQuery()
            ->where('storage_account_id', $accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->delete();
    }
}
