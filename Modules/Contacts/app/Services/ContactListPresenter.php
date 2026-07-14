<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Contact;

final class ContactListPresenter
{
    public function __construct(
        private readonly ContactCatalogCountsService $catalogCounts,
        private readonly ContactCrawlStateService $crawlState,
        private readonly ContactDownloadCountsService $downloadCounts,
        private readonly ContactAnnotationService $annotations,
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
     *     crawl_state: array<string, array{processing: bool, crawled: bool, fetched?: int, total?: int|null}>,
     *     starred: bool,
     *     note: string|null,
     *     note_preview: string|null
     * }>
     */
    public function present(Connection $connection, array $contacts): array
    {
        $contactNsids = array_map(fn (Contact $contact): string => $contact->nsid, $contacts);
        $counts = $this->catalogCounts->forContacts($connection, $contactNsids);
        $crawlStates = $this->crawlState->forContacts($connection, $contactNsids, $counts);
        $downloads = $this->downloadCounts->forContacts($connection, $contactNsids);
        $annotationMap = $this->annotations->mapForContacts($connection->connection_key, $contactNsids);

        return array_map(function (Contact $contact) use ($counts, $crawlStates, $downloads, $annotationMap): array {
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

            $annotation = $annotationMap[$contact->nsid] ?? [
                'note' => null,
                'starred' => false,
                'note_preview' => null,
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
                'starred' => $annotation['starred'],
                'note' => $annotation['note'],
                'note_preview' => $annotation['note_preview'],
            ];
        }, $contacts);
    }
}
