<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SpiderFrontierStatus;
use App\Models\SpiderFrontierItem;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;

final class SpiderFrontierRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(SpiderFrontierItem $model)
    {
        parent::__construct($model);
    }

    public function enqueue(int $spiderRunId, string $contactNsid, int $depth): bool
    {
        $created = $this->newQuery()->firstOrCreate(
            [
                'spider_run_id' => $spiderRunId,
                'contact_nsid' => $contactNsid,
            ],
            [
                'depth' => $depth,
                'status' => SpiderFrontierStatus::Pending->value,
            ],
        );

        return $created->wasRecentlyCreated;
    }

    /**
     * @return Collection<int, SpiderFrontierItem>
     */
    public function nextPending(int $spiderRunId, int $limit): Collection
    {
        /** @var Collection<int, SpiderFrontierItem> */
        return $this->newQuery()
            ->where('spider_run_id', $spiderRunId)
            ->where('status', SpiderFrontierStatus::Pending->value)
            ->orderBy('depth')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function depthHistogram(int $spiderRunId): array
    {
        return $this->newQuery()
            ->where('spider_run_id', $spiderRunId)
            ->selectRaw('depth, count(*) as aggregate')
            ->groupBy('depth')
            ->orderBy('depth')
            ->pluck('aggregate', 'depth')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public function countByStatus(int $spiderRunId, SpiderFrontierStatus $status): int
    {
        return $this->newQuery()
            ->where('spider_run_id', $spiderRunId)
            ->where('status', $status->value)
            ->count();
    }

    /**
     * @return list<string>
     */
    public function knownContactNsids(int $spiderRunId): array
    {
        return $this->newQuery()
            ->where('spider_run_id', $spiderRunId)
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }
}
