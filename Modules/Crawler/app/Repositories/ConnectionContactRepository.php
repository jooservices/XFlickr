<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\ConnectionContact;

final class ConnectionContactRepository
{
    /**
     * @param  list<string>  $contactNsids
     */
    public function upsertMany(string $connectionKey, array $contactNsids, ?int $chunk = null): int
    {
        if ($contactNsids === []) {
            return 0;
        }

        $chunkSize = $chunk ?? (int) config('xflickr-crawler.bulk.chunk_size', 250);
        $table = (new ConnectionContact)->getTable();
        $now = now()->toDateTimeString();
        $total = 0;

        foreach (array_chunk(array_unique($contactNsids), $chunkSize) as $batch) {
            $payload = [];
            foreach ($batch as $nsid) {
                if ($nsid === '') {
                    continue;
                }

                $payload[] = [
                    'connection_key' => $connectionKey,
                    'contact_nsid' => $nsid,
                    'discovered_at' => $now,
                ];
            }

            if ($payload === []) {
                continue;
            }

            DB::table($table)->upsert(
                $payload,
                ['connection_key', 'contact_nsid'],
                ['discovered_at'],
            );
            $total += count($payload);
        }

        return $total;
    }

    /**
     * @return Collection<int, ConnectionContact>
     */
    public function listByConnectionKey(string $connectionKey): Collection
    {
        return ConnectionContact::query()
            ->where('connection_key', $connectionKey)
            ->orderBy('discovered_at')
            ->get();
    }
}
