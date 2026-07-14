<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Modules\Crawler\Models\ConnectionContact;

final class ConnectionContactQueryRepository
{
    public function existsForConnection(string $connectionKey, string $contactNsid): bool
    {
        return ConnectionContact::query()
            ->forConnection($connectionKey)
            ->where('contact_nsid', $contactNsid)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function nsidsForConnection(string $connectionKey): array
    {
        return ConnectionContact::query()
            ->forConnection($connectionKey)
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }

    /**
     * @param  list<string>  $excludeNsids
     * @return list<string>
     */
    public function nsidsForConnectionExcept(string $connectionKey, array $excludeNsids, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $query = ConnectionContact::query()
            ->forConnection($connectionKey)
            ->orderBy('contact_nsid')
            ->limit($limit);

        if ($excludeNsids !== []) {
            $query->whereNotIn('contact_nsid', $excludeNsids);
        }

        return $query
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }

    /**
     * @param  list<string>  $nsids
     * @return list<string>
     */
    public function filterNsidsForConnection(string $connectionKey, array $nsids): array
    {
        if ($nsids === []) {
            return [];
        }

        return ConnectionContact::query()
            ->forConnection($connectionKey)
            ->whereIn('contact_nsid', $nsids)
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }

    public function countForConnection(string $connectionKey): int
    {
        return ConnectionContact::query()
            ->forConnection($connectionKey)
            ->count();
    }
}
