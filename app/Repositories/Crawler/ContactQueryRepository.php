<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Crawler\Models\ConnectionContact;
use Modules\Crawler\Models\Contact;

final class ContactQueryRepository
{
    /**
     * @return Builder<Contact>
     */
    public function queryForConnection(string $connectionKey): Builder
    {
        return Contact::query()
            ->whereIn(
                'nsid',
                ConnectionContact::query()
                    ->forConnection($connectionKey)
                    ->select('contact_nsid'),
            );
    }

    /**
     * @return Builder<Contact>
     */
    public function queryForConnectionWithSearch(string $connectionKey, ?string $search): Builder
    {
        $query = $this->queryForConnection($connectionKey);

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder
                    ->where('username', 'like', $like)
                    ->orWhere('realname', 'like', $like)
                    ->orWhere('nsid', 'like', $like);
            });
        }

        return $query;
    }

    public function countForConnection(string $connectionKey): int
    {
        return $this->queryForConnection($connectionKey)->count();
    }

    /**
     * @param  list<string>  $connectionKeys
     * @return array<string, int>
     */
    public function countsGroupedByConnection(array $connectionKeys): array
    {
        if ($connectionKeys === []) {
            return [];
        }

        return ConnectionContact::query()
            ->whereIn('connection_key', $connectionKeys)
            ->selectRaw('connection_key, count(*) as aggregate')
            ->groupBy('connection_key')
            ->pluck('aggregate', 'connection_key')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $nsids
     * @return list<Contact>
     */
    public function listForConnectionByNsids(string $connectionKey, array $nsids): array
    {
        if ($nsids === []) {
            return [];
        }

        return $this->queryForConnection($connectionKey)
            ->whereIn('nsid', $nsids)
            ->get()
            ->all();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function suggestForConnection(string $connectionKey, string $search, int $limit): Collection
    {
        $like = '%'.$search.'%';

        $suggestions = $this->queryForConnection($connectionKey)
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('username', 'like', $like)
                    ->orWhere('realname', 'like', $like)
                    ->orWhere('nsid', 'like', $like);
            })
            ->orderBy('username')
            ->limit($limit)
            ->get(['nsid', 'username', 'realname'])
            ->map(fn (Contact $contact): array => [
                'nsid' => $contact->nsid,
                'username' => $contact->username,
                'realname' => $contact->realname,
            ])
            ->values()
            ->all();

        /** @var list<array{nsid: string, username: string|null, realname: string|null}> $suggestions */
        return collect($suggestions);
    }

    /**
     * @param  list<string>  $nsids
     * @return list<Contact>
     */
    public function listByNsids(array $nsids): array
    {
        if ($nsids === []) {
            return [];
        }

        return Contact::query()
            ->whereIn('nsid', $nsids)
            ->get()
            ->all();
    }

    /**
     * Lightweight contact rows for graph / list UI (avoids hydrating raw_payload).
     *
     * @param  list<string>  $nsids
     * @return list<Contact>
     */
    public function listSummariesByNsids(array $nsids): array
    {
        if ($nsids === []) {
            return [];
        }

        return Contact::query()
            ->whereIn('nsid', $nsids)
            ->get(['nsid', 'username', 'realname'])
            ->all();
    }

    public function findByNsid(string $nsid): Contact
    {
        return Contact::query()->where('nsid', $nsid)->firstOrFail();
    }
}
