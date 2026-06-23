<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Collection;
use JOOservices\LaravelConfig\Models\Config as ConfigModel;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;

final class ConfigEntryRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(ConfigModel $model)
    {
        parent::__construct($model);
    }

    public function deleteByGroupAndKey(string $group, string $key): void
    {
        $this->newQuery()->where('group', $group)->where('key', $key)->delete();
    }

    /**
     * @return Collection<int, ConfigModel>
     */
    public function listOrdered(): Collection
    {
        return $this->newQuery()
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }
}
