<?php

declare(strict_types=1);

namespace Modules\Crawler\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;

final class ContactRepository
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows, ?int $chunk = null): int
    {
        if ($rows === []) {
            return 0;
        }

        $chunkSize = $chunk ?? (int) config('xflickr-crawler.bulk.chunk_size', 250);

        $table = (new Contact)->getTable();
        $now = now();
        $total = 0;

        foreach (array_chunk($rows, $chunkSize) as $batch) {
            $payload = [];
            foreach ($batch as $row) {
                $payload[] = array_merge($row, [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]);
            }

            DB::table($table)->upsert(
                $payload,
                ['nsid'],
                ['username', 'realname', 'friend', 'family', 'raw_payload', 'updated_at'],
            );
            $total += count($batch);
        }

        return $total;
    }

    /**
     * @param  list<string>  $nsids
     * @return Collection<int, Contact>
     */
    public function findByNsidsOrderedByUsername(array $nsids): Collection
    {
        if ($nsids === []) {
            return new Collection;
        }

        return Contact::query()
            ->whereIn('nsid', $nsids)
            ->orderBy('username')
            ->get();
    }

    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public function paginateProfilesForConnection(
        string $connectionKey,
        ?string $search,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        $contactsTable = (new Contact)->getTable();
        $connectionContactsTable = (new ConnectionContact)->getTable();

        $query = Contact::query()
            ->select("{$contactsTable}.*")
            ->join(
                $connectionContactsTable,
                "{$connectionContactsTable}.contact_nsid",
                '=',
                "{$contactsTable}.nsid",
            )
            ->where("{$connectionContactsTable}.connection_key", $connectionKey);

        if ($search !== null && $search !== '') {
            $like = '%'.$this->escapeLike($search).'%';
            $query->where(function ($builder) use ($contactsTable, $like): void {
                $builder
                    ->where("{$contactsTable}.username", 'like', $like)
                    ->orWhere("{$contactsTable}.realname", 'like', $like)
                    ->orWhere("{$contactsTable}.nsid", 'like', $like);
            });
        }

        return $query
            ->orderBy("{$contactsTable}.username")
            ->paginate($perPage, ["{$contactsTable}.*"], 'page', $page);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
