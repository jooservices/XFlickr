<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\CatalogQueryRepository;
use App\Repositories\Crawler\PhotoQueryRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Contact;
use Modules\Transfer\Repositories\StoredFileRepository;

final class ContactListSorter
{
    /** @var list<string> */
    public const SORTABLE_COLUMNS = [
        'nsid',
        'username',
        'photos_count',
        'favorites_count',
        'photosets_count',
        'galleries_count',
        'downloads_count',
    ];

    public function __construct(
        private readonly PhotoQueryRepository $photos,
        private readonly CatalogQueryRepository $catalog,
        private readonly StoredFileRepository $storedFiles,
    ) {}

    /**
     * @param  Builder<Contact>  $query
     * @return Builder<Contact>
     */
    public function apply(Builder $query, Connection $connection, string $sort, string $direction): Builder
    {
        $sort = in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'username';
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $table = (new Contact)->getTable();

        return match ($sort) {
            'nsid' => $query->orderBy("{$table}.nsid", $direction),
            'username' => $query->orderBy("{$table}.username", $direction),
            'photos_count' => $this->orderBySubqueryCount(
                $query,
                $table,
                $this->photos->ownerCountSubquery(),
                $direction,
            ),
            'photosets_count' => $this->orderBySubqueryCount(
                $query,
                $table,
                $this->catalog->photosetCountSubquery(),
                $direction,
            ),
            'galleries_count' => $this->orderBySubqueryCount(
                $query,
                $table,
                $this->catalog->galleryCountSubquery(),
                $direction,
            ),
            'favorites_count' => $this->orderBySubqueryCount(
                $query,
                $table,
                $this->catalog->favoriteCountSubquery($connection->connection_key),
                $direction,
            ),
            'downloads_count' => $this->orderBySubqueryCount(
                $query,
                $table,
                $this->storedFiles->completedOriginalCountSubquery(),
                $direction,
            ),
        };
    }

    /**
     * @param  Builder<Contact>  $query
     * @param  Builder<covariant Model>  $countQuery
     * @return Builder<Contact>
     */
    private function orderBySubqueryCount(Builder $query, string $table, Builder $countQuery, string $direction): Builder
    {
        $alias = 'sort_counts';

        return $query
            ->leftJoinSub($countQuery, $alias, "{$alias}.contact_nsid", '=', "{$table}.nsid")
            ->select("{$table}.*")
            ->orderByRaw('COALESCE('.$alias.'.aggregate, 0) '.$direction)
            ->orderBy("{$table}.username", 'asc');
    }
}
