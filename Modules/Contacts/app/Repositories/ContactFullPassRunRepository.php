<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Spider\Contracts\ConcurrentRunGuard;
use Modules\Spider\Contracts\RunWriteRepository;

/**
 * @extends EloquentRepository<ContactFullPassRun>
 */
final class ContactFullPassRunRepository extends EloquentRepository implements ConcurrentRunGuard, RunWriteRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(ContactFullPassRun $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRun(Model $run, array $attributes): void
    {
        $this->update((int) $run->getKey(), $attributes);
        $run->fill($attributes);
    }

    public function incrementRun(Model $run, string $column, int $amount = 1): void
    {
        $this->newQuery()->whereKey($run->getKey())->increment($column, $amount);
        $run->setAttribute($column, (int) $run->getAttribute($column) + $amount);
    }

    public function hasActiveRun(string $connectionKey): bool
    {
        return $this->findActiveForConnection($connectionKey) !== null;
    }

    public function findActiveForConnection(string $connectionKey): ?ContactFullPassRun
    {
        $run = $this->newQuery()
            ->forConnection($connectionKey)
            ->running()
            ->latest('id')
            ->first();

        return $run instanceof ContactFullPassRun ? $run : null;
    }

    public function findLatestForConnection(string $connectionKey): ?ContactFullPassRun
    {
        $run = $this->newQuery()
            ->forConnection($connectionKey)
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
            ->running()
            ->orderBy('id')
            ->get();
    }
}
