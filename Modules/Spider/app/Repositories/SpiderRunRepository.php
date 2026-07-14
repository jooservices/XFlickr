<?php

declare(strict_types=1);

namespace Modules\Spider\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;
use Modules\Spider\Contracts\RunWriteRepository;
use Modules\Spider\Models\SpiderRun;

/**
 * @extends EloquentRepository<SpiderRun>
 */
final class SpiderRunRepository extends EloquentRepository implements RunWriteRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(SpiderRun $model)
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

    public function findActiveForConnection(string $connectionKey): ?SpiderRun
    {
        $run = $this->newQuery()
            ->forConnection($connectionKey)
            ->running()
            ->latest('id')
            ->first();

        return $run instanceof SpiderRun ? $run : null;
    }

    public function findLatestForConnection(string $connectionKey): ?SpiderRun
    {
        $run = $this->newQuery()
            ->forConnection($connectionKey)
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
            ->running()
            ->orderBy('id')
            ->get();
    }
}
