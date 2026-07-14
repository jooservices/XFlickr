<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Flickr\Exceptions\FlickrUrlResolutionException;
use Modules\Flickr\Services\FlickrAccountsService;
use Modules\Flickr\Support\ConnectionPresenter;

final class ContactImportService
{
    public function __construct(
        private readonly FlickrAccountsService $flickrAccounts,
        private readonly FlickrCatalogService $catalog,
        private readonly ContactListQueryService $contactList,
    ) {}

    /**
     * @param  list<CrawlType>  $crawlTypes
     * @return array{
     *     nsid: string,
     *     username: string|null,
     *     realname: string|null,
     *     already_linked: bool,
     *     crawl_started: bool,
     *     redirect_path: string
     * }
     *
     * @throws FlickrUrlResolutionException
     */
    public function import(
        Connection $connection,
        string $url,
        bool $startCrawl = true,
        array $crawlTypes = [],
    ): array {
        $row = $this->flickrAccounts->resolveContactFromUrl($connection, $url);
        $nsid = (string) $row['nsid'];
        $alreadyLinked = $this->contactList->isLinked($connection, $nsid);

        $this->catalog->persistContacts([$row], $connection->connection_key);

        $crawlStarted = false;
        if ($startCrawl) {
            $types = $crawlTypes !== []
                ? $crawlTypes
                : [CrawlType::Photos, CrawlType::Photosets, CrawlType::Galleries, CrawlType::Favorites];
            $this->flickrAccounts->crawlMany($connection, $types, $nsid);
            $crawlStarted = true;
        }

        $publicId = ConnectionPresenter::toArray($connection)['public_id'];

        return [
            'nsid' => $nsid,
            'username' => isset($row['username']) && is_string($row['username']) ? $row['username'] : null,
            'realname' => isset($row['realname']) && is_string($row['realname']) ? $row['realname'] : null,
            'already_linked' => $alreadyLinked,
            'crawl_started' => $crawlStarted,
            'redirect_path' => '/flickr/accounts/'.$publicId.'/contacts/'.rawurlencode($nsid),
        ];
    }
}
