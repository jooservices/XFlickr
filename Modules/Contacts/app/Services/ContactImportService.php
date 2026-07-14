<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Contacts\Dto\ContactImportResult;
use Modules\Crawler\Enums\CrawlType;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Services\FlickrCatalogService;
use Modules\Flickr\Exceptions\FlickrUrlResolutionException;
use Modules\Flickr\Services\FlickrAccountsService;

final class ContactImportService
{
    public function __construct(
        private readonly FlickrAccountsService $flickrAccounts,
        private readonly FlickrCatalogService $catalog,
        private readonly ContactListQueryService $contactList,
    ) {}

    /**
     * @param  list<CrawlType>  $crawlTypes
     *
     * @throws FlickrUrlResolutionException
     */
    public function import(
        Connection $connection,
        string $url,
        bool $startCrawl = true,
        array $crawlTypes = [],
    ): ContactImportResult {
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

        $publicId = $this->flickrAccounts->ensurePublicId($connection);

        return new ContactImportResult(
            nsid: $nsid,
            username: isset($row['username']) && is_string($row['username']) ? $row['username'] : null,
            realname: isset($row['realname']) && is_string($row['realname']) ? $row['realname'] : null,
            alreadyLinked: $alreadyLinked,
            crawlStarted: $crawlStarted,
            redirectPath: route('flickr.accounts.contacts.show', [
                'connection' => $publicId,
                'contactNsid' => $nsid,
            ], absolute: false),
        );
    }
}
