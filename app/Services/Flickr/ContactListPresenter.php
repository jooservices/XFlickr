<?php

declare(strict_types=1);

namespace App\Services\Flickr;

use JOOservices\XFlickrCrawler\Models\Connection;
use JOOservices\XFlickrCrawler\Models\Contact;

final class ContactListPresenter
{
    public function __construct(
        private readonly ContactCatalogCountsService $catalogCounts,
        private readonly ContactCrawlStateService $crawlState,
        private readonly ContactDownloadCountsService $downloadCounts,
    ) {}

    /**
     * @param  list<Contact>  $contacts
     * @return list<array{
     *     nsid: string,
     *     username: string|null,
     *     realname: string|null,
     *     photos_count: int,
     *     favorites_count: int,
     *     photosets_count: int,
     *     galleries_count: int,
     *     downloads_count: int,
     *     downloads_failed_count: int,
     *     download_state: array{processing: bool, batch_completed?: int, batch_total?: int},
     *     crawl_state: array<string, array{processing: bool, crawled: bool, fetched?: int, total?: int|null}>
     * }>
     */
    public function present(Connection $connection, array $contacts): array
    {
        $contactNsids = array_map(fn (Contact $contact): string => $contact->nsid, $contacts);
        $counts = $this->catalogCounts->forContacts($connection, $contactNsids);
        $crawlStates = $this->crawlState->forContacts($connection, $contactNsids, $counts);
        $downloads = $this->downloadCounts->forContacts($connection, $contactNsids);

        return array_map(function (Contact $contact) use ($counts, $crawlStates, $downloads): array {
            $catalog = $counts[$contact->nsid] ?? [
                'photos' => 0,
                'photosets' => 0,
                'galleries' => 0,
                'favorites' => 0,
            ];

            $download = $downloads[$contact->nsid] ?? [
                'total' => 0,
                'failed' => 0,
                'processing' => false,
            ];

            return [
                'nsid' => $contact->nsid,
                'username' => $contact->username,
                'realname' => $contact->realname,
                'photos_count' => $catalog['photos'],
                'favorites_count' => $catalog['favorites'],
                'photosets_count' => $catalog['photosets'],
                'galleries_count' => $catalog['galleries'],
                'downloads_count' => $download['total'],
                'downloads_failed_count' => $download['failed'],
                'download_state' => [
                    'processing' => $download['processing'],
                    ...($download['processing'] ? [
                        'batch_completed' => $download['batch_completed'] ?? 0,
                        'batch_total' => $download['batch_total'] ?? 0,
                    ] : []),
                ],
                'crawl_state' => $crawlStates[$contact->nsid] ?? [],
            ];
        }, $contacts);
    }
}
