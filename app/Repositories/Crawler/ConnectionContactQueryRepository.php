<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use JOOservices\XFlickrCrawler\Models\ConnectionContact;

final class ConnectionContactQueryRepository
{
    public function existsForConnection(string $connectionKey, string $contactNsid): bool
    {
        return ConnectionContact::query()
            ->where('connection_key', $connectionKey)
            ->where('contact_nsid', $contactNsid)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function nsidsForConnection(string $connectionKey): array
    {
        return ConnectionContact::query()
            ->where('connection_key', $connectionKey)
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }
}
