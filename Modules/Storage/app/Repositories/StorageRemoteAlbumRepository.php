<?php

declare(strict_types=1);

namespace Modules\Storage\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Storage\Models\StorageRemoteAlbum;

/**
 * @extends EloquentRepository<StorageRemoteAlbum>
 */
final class StorageRemoteAlbumRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StorageRemoteAlbum $model)
    {
        parent::__construct($model);
    }

    /**
     * @return LengthAwarePaginator<int, StorageRemoteAlbum>
     */
    public function paginateForParent(int $accountId, string $parentRemoteId, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->newQuery()
            ->forAccount($accountId)
            ->where('parent_remote_id', $parentRemoteId)
            ->orderBy('title')
            ->paginate($perPage, ['*'], 'album_page', max(1, $page));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByRemoteId(int $accountId, string $remoteId, array $attributes): StorageRemoteAlbum
    {
        return $this->newQuery()->updateOrCreate(
            [
                'storage_account_id' => $accountId,
                'remote_id' => $remoteId,
            ],
            $attributes,
        );
    }

    public function deleteAllForAccount(int $accountId): void
    {
        $this->newQuery()
            ->forAccount($accountId)
            ->delete();
    }
}
