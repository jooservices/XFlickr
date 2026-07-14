<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\SubjectContact;

final class SubjectContactRepository
{
    /**
     * @param  list<string>  $contactNsids
     */
    public function upsertMany(
        string $connectionKey,
        string $subjectNsid,
        array $contactNsids,
        ?int $crawlRunId = null,
        ?int $chunk = null,
    ): int {
        if ($contactNsids === []) {
            return 0;
        }

        $chunkSize = $chunk ?? (int) config('xflickr-crawler.bulk.chunk_size', 250);
        $table = (new SubjectContact)->getTable();
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
                    'subject_nsid' => $subjectNsid,
                    'contact_nsid' => $nsid,
                    'crawl_run_id' => $crawlRunId,
                    'discovered_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($payload === []) {
                continue;
            }

            DB::table($table)->upsert(
                $payload,
                ['connection_key', 'subject_nsid', 'contact_nsid'],
                ['crawl_run_id', 'discovered_at', 'updated_at'],
            );
            $total += count($payload);
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    public function discoveredForCrawlRun(int $crawlRunId): array
    {
        return SubjectContact::query()
            ->where('crawl_run_id', $crawlRunId)
            ->orderBy('contact_nsid')
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function discoveredContactNsids(string $connectionKey, string $subjectNsid): array
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->orderBy('contact_nsid')
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }
}
