<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Crawler\Models\Connection;
use Modules\Flickr\Support\ConnectionPresenter;
use Modules\Flickr\Support\ContactPresenter;

final class ContactDetailService
{
    public function __construct(
        private readonly ContactCatalogCountsService $catalogCounts,
        private readonly ContactCrawlStateService $crawlState,
        private readonly ContactCatalogDetailStatsService $detailStats,
        private readonly ContactListQueryService $contactList,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function forShow(Connection $connection, string $contactNsid): ?array
    {
        if (! $this->contactList->isLinked($connection, $contactNsid)) {
            return null;
        }

        $contact = $this->contactList->findContact($contactNsid);
        $counts = $this->catalogCounts->forContacts($connection, [$contactNsid])[$contactNsid] ?? [
            'photos' => 0,
            'photosets' => 0,
            'galleries' => 0,
            'favorites' => 0,
        ];

        return [
            'account' => ConnectionPresenter::toArray($connection),
            'contact' => ContactPresenter::toDetailArray($contact),
            'catalog_stats' => $this->detailStats->forContact($connection, $contactNsid),
            'crawl_state' => $this->crawlState->forContact($connection, $contactNsid, [$contactNsid => $counts]),
        ];
    }
}
