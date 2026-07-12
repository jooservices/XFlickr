<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Contact;
use Modules\Contacts\Repositories\ContactAnnotationRepository;

final class ContactListQueryService
{
    public function __construct(
        private readonly ContactQueryRepository $contacts,
        private readonly ConnectionContactQueryRepository $connectionContacts,
        private readonly ContactAnnotationRepository $annotations,
        private readonly ContactListSorter $sorter,
    ) {}

    public function paginateForConnection(
        Connection $connection,
        ?string $search,
        string $sort,
        string $direction,
        int $perPage,
        int $page,
        bool $starredOnly = false,
    ): LengthAwarePaginator {
        $query = $search !== null && $search !== ''
            ? $this->contacts->queryForConnectionWithSearch($connection->connection_key, $search)
            : $this->contacts->queryForConnection($connection->connection_key);

        if ($starredOnly) {
            $starredNsids = $this->annotations->starredContactNsids($connection->connection_key);

            if ($starredNsids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('nsid', $starredNsids);
            }
        }

        $query = $this->sorter->apply($query, $connection, $sort, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  list<string>  $nsids
     * @return list<Contact>
     */
    public function listByNsids(Connection $connection, array $nsids): array
    {
        return $this->contacts->listForConnectionByNsids($connection->connection_key, $nsids);
    }

    /**
     * @return Collection<int, array{nsid: string, username: string|null, realname: string|null}>
     */
    public function suggest(Connection $connection, string $search, int $limit): Collection
    {
        return $this->contacts->suggestForConnection($connection->connection_key, $search, $limit);
    }

    public function isLinked(Connection $connection, string $contactNsid): bool
    {
        return $this->connectionContacts->existsForConnection($connection->connection_key, $contactNsid);
    }

    public function findContact(string $contactNsid): Contact
    {
        return $this->contacts->findByNsid($contactNsid);
    }
}
