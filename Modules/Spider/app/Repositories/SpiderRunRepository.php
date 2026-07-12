<?php

declare(strict_types=1);

namespace Modules\Spider\Repositories;

use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;

final class SpiderRunRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(SpiderRun $model)
    {
        parent::__construct($model);
    }

    public function findActiveForConnection(string $connectionKey): ?SpiderRun
    {
        $run = $this->newQuery()
            ->where('connection_key', $connectionKey)
            ->where('status', SpiderRunStatus::Running->value)
            ->latest('id')
            ->first();

        return $run instanceof SpiderRun ? $run : null;
    }

    public function findLatestForConnection(string $connectionKey): ?SpiderRun
    {
        $run = $this->newQuery()
            ->where('connection_key', $connectionKey)
            ->latest('id')
            ->first();

        return $run instanceof SpiderRun ? $run : null;
    }

    /**
     * @return Collection<int, SpiderRun>
     */
    public function listRunning(): Collection
    {
        /** @var Collection<int, SpiderRun> */
        return $this->newQuery()
            ->where('status', SpiderRunStatus::Running->value)
            ->orderBy('id')
            ->get();
    }
}
