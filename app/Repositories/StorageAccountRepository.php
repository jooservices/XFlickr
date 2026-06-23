<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\StorageAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Jooservices\LaravelRepository\Traits\HasFilter;

final class StorageAccountRepository extends EloquentRepository
{
    use HasCrud;
    use HasFilter;

    public function __construct(StorageAccount $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?StorageAccount
    {
        return $this->newQuery()->find($id);
    }

    public function findByIdOrFail(int $id): StorageAccount
    {
        return $this->newQuery()->findOrFail($id);
    }

    public function findDefault(): ?StorageAccount
    {
        return $this->newQuery()->where('is_default', true)->first();
    }

    /**
     * @return Collection<int, StorageAccount>
     */
    public function listOrderedForSettings(): Collection
    {
        return $this->newQuery()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, StorageAccount>
     */
    public function listForProvider(string $provider): Collection
    {
        return $this->newQuery()
            ->where('provider', $provider)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function countForProvider(string $provider): int
    {
        return $this->newQuery()->where('provider', $provider)->count();
    }

    public function hasDefaultForProvider(string $provider): bool
    {
        return $this->newQuery()
            ->where('provider', $provider)
            ->where('is_default', true)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreateByProviderAndLabel(string $provider, string $label, array $attributes): StorageAccount
    {
        return $this->newQuery()->updateOrCreate(
            ['provider' => $provider, 'label' => $label],
            $attributes,
        );
    }

    public function promoteFirstAsDefault(string $provider): void
    {
        $this->newQuery()->where('provider', $provider)->orderBy('id')->first()?->update(['is_default' => true]);
    }

    public function clearDefaultForProvider(string $provider): void
    {
        $this->newQuery()
            ->where('provider', $provider)
            ->update(['is_default' => false]);
    }

    public function connectInTransaction(callable $callback): StorageAccount
    {
        return DB::transaction($callback);
    }
}
