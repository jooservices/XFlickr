<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Spider\Contracts\ConcurrentRunGuard;
use Modules\Spider\Enums\SpiderRunStatus;

/**
 * @extends EloquentRepository<ContactFullPassRun>
 */
final class ContactFullPassRunRepository extends EloquentRepository implements ConcurrentRunGuard
{
    use HasCrud;
    use HasFilter;

    public function __construct(ContactFullPassRun $model)
    {
        parent::__construct($model);
    }

    public function hasActiveRun(string $connectionKey): bool
    {
        return $this->findActiveForConnection($connectionKey) !== null;
    }

    public function findActiveForConnection(string $connectionKey): ?ContactFullPassRun
    {
        $run = $this->newQuery()
            ->where('connection_key', $connectionKey)
            ->where('status', SpiderRunStatus::Running->value)
            ->latest('id')
            ->first();

        return $run instanceof ContactFullPassRun ? $run : null;
    }

    public function findLatestForConnection(string $connectionKey): ?ContactFullPassRun
    {
        $run = $this->newQuery()
            ->where('connection_key', $connectionKey)
            ->latest('id')
            ->first();

        return $run instanceof ContactFullPassRun ? $run : null;
    }

    /**
     * @return Collection<int, ContactFullPassRun>
     */
    public function listRunning(): Collection
    {
        /** @var Collection<int, ContactFullPassRun> */
        return $this->newQuery()
            ->where('status', SpiderRunStatus::Running->value)
            ->orderBy('id')
            ->get();
    }
}
