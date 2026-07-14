<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Contacts\Models\ContactFullPassFrontierItem;
use Modules\Spider\Contracts\FrontierRepositoryContract;
use Modules\Spider\Enums\SpiderFrontierStatus;

/**
 * @extends EloquentRepository<ContactFullPassFrontierItem>
 */
final class ContactFullPassFrontierRepository extends EloquentRepository implements FrontierRepositoryContract
{
    use HasCrud;
    use HasFilter;

    public function __construct(ContactFullPassFrontierItem $model)
    {
        parent::__construct($model);
    }

    public function enqueue(int $runId, string $contactNsid, int $depth): bool
    {
        $created = $this->newQuery()->firstOrCreate(
            [
                'contact_full_pass_run_id' => $runId,
                'contact_nsid' => $contactNsid,
            ],
            [
                'depth' => $depth,
                'status' => SpiderFrontierStatus::Pending->value,
            ],
        );

        return $created->wasRecentlyCreated;
    }

    public function findByRunAndContactNsid(int $runId, string $contactNsid): ?ContactFullPassFrontierItem
    {
        $item = $this->newQuery()
            ->where('contact_full_pass_run_id', $runId)
            ->where('contact_nsid', $contactNsid)
            ->first();

        return $item instanceof ContactFullPassFrontierItem ? $item : null;
    }

    /**
     * @return Collection<int, ContactFullPassFrontierItem>
     */
    public function nextPending(int $runId, int $limit): Collection
    {
        /** @var Collection<int, ContactFullPassFrontierItem> */
        return $this->newQuery()
            ->where('contact_full_pass_run_id', $runId)
            ->pending()
            ->orderBy('depth')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function countByStatus(int $runId, SpiderFrontierStatus $status): int
    {
        return $this->newQuery()
            ->where('contact_full_pass_run_id', $runId)
            ->withStatus($status)
            ->count();
    }

    /**
     * @return list<string>
     */
    public function knownContactNsids(int $runId): array
    {
        return $this->newQuery()
            ->where('contact_full_pass_run_id', $runId)
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }
}
