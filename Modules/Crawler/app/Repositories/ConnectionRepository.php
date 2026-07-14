<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Crawler\Models\Connection;

final class ConnectionRepository
{
    public function findByKey(string $connectionKey): ?Connection
    {
        return Connection::query()
            ->byConnectionKey($connectionKey)
            ->first();
    }

    public function findActive(): ?Connection
    {
        return Connection::query()
            ->active()
            ->latest('connected_at')
            ->first();
    }

    /**
     * @return Collection<int, Connection>
     */
    public function listOrderedByConnectedAt(): Collection
    {
        return Connection::query()
            ->orderByDesc('connected_at')
            ->get();
    }

    public function deactivateAll(): void
    {
        Connection::query()->update(['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreateByKey(string $connectionKey, array $attributes): Connection
    {
        $connection = Connection::query()->updateOrCreate(
            ['connection_key' => $connectionKey],
            $attributes,
        );

        return $connection->fresh() ?? $connection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateByKey(string $connectionKey, array $attributes): int
    {
        return Connection::query()
            ->byConnectionKey($connectionKey)
            ->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateById(int|string $id, array $attributes): int
    {
        return Connection::query()
            ->whereKey($id)
            ->update($attributes);
    }

    public function disconnect(string $connectionKey): void
    {
        $connection = $this->findByKey($connectionKey);
        if ($connection === null) {
            return;
        }

        $connection->update([
            'token_payload' => '',
            'is_active' => false,
            'disconnected_at' => now(),
        ]);
    }

    public function activate(string $connectionKey): void
    {
        $this->deactivateAll();

        $this->updateByKey($connectionKey, [
            'is_active' => true,
            'disconnected_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Connection $connection, array $attributes): Connection
    {
        $connection->update($attributes);

        return $connection->fresh() ?? $connection;
    }
}
