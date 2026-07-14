<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\SubjectContactQueryRepository;
use Modules\Contacts\Repositories\ContactAnnotationRepository;
use Modules\Contacts\Support\ContactGraphRuntimeConfig;
use Modules\Crawler\Enums\CrawlRunStatus;
use Modules\Crawler\Models\Connection;
use Modules\Crawler\Models\Contact;
use Modules\Crawler\Models\CrawlRun;

final class ContactGraphQueryService
{
    public function __construct(
        private readonly ConnectionContactQueryRepository $connectionContacts,
        private readonly SubjectContactQueryRepository $subjectContacts,
        private readonly ContactQueryRepository $contacts,
        private readonly ContactAnnotationService $annotations,
        private readonly ContactAnnotationRepository $annotationRecords,
        private readonly ContactCatalogCountsService $catalogCounts,
        private readonly ContactGraphRuntimeConfig $graphConfig,
    ) {}

    /**
     * @return array{
     *     root_nsid: string,
     *     nodes: list<array{
     *         nsid: string,
     *         label: string,
     *         username: string|null,
     *         realname: string|null,
     *         is_root: bool,
     *         starred: bool,
     *         note_preview: string|null,
     *         child_count: int,
     *         photos_count: int
     *     }>,
     *     edges: list<array{id: int, from: string, to: string}>,
     *     meta: array{
     *         direct_total: int,
     *         direct_shown: int,
     *         initial_direct_limit: int,
     *         load_more_step: int,
     *         subject_edges_total: int,
     *         subject_edges_shown: int,
     *         has_more_direct: bool
     *     }
     * }
     */
    public function snapshot(Connection $connection, int $directLimit): array
    {
        $rootNsid = $connection->connection_key;
        $allDirectNsids = $this->connectionContacts->nsidsForConnection($rootNsid);
        $directTotal = count($allDirectNsids);
        $selectedDirectNsids = $this->selectDirectContactNsids($connection, $allDirectNsids, $directLimit);
        $visibleNsids = array_values(array_unique([$rootNsid, ...$selectedDirectNsids]));
        $visibleSet = array_fill_keys($visibleNsids, true);

        $edges = [];
        $edgeId = 0;

        foreach ($selectedDirectNsids as $contactNsid) {
            $edges[] = [
                'id' => ++$edgeId,
                'from' => $rootNsid,
                'to' => $contactNsid,
            ];
        }

        $allSubjectEdges = $this->subjectContacts->listEdgesForConnection($rootNsid);
        $subjectEdgesShown = 0;

        foreach ($allSubjectEdges as $edge) {
            if (! isset($visibleSet[$edge['subject_nsid']])) {
                continue;
            }

            $edges[] = [
                'id' => (int) $edge['id'],
                'from' => $edge['subject_nsid'],
                'to' => $edge['contact_nsid'],
            ];
            $subjectEdgesShown++;

            if (! isset($visibleSet[$edge['contact_nsid']])) {
                $visibleSet[$edge['contact_nsid']] = true;
                $visibleNsids[] = $edge['contact_nsid'];
            }
        }

        $directShown = count($selectedDirectNsids);

        return [
            'root_nsid' => $rootNsid,
            'nodes' => $this->buildNodes($connection, $rootNsid, $visibleNsids),
            'edges' => $edges,
            'meta' => [
                'direct_total' => $directTotal,
                'direct_shown' => $directShown,
                'initial_direct_limit' => $this->graphConfig->initialDirectLimit(),
                'load_more_step' => $this->graphConfig->loadMoreStep(),
                'subject_edges_total' => count($allSubjectEdges),
                'subject_edges_shown' => $subjectEdgesShown,
                'has_more_direct' => $directShown < $directTotal,
            ],
        ];
    }

    /**
     * @return array{
     *     edges: list<array{id: int, from: string, to: string}>,
     *     nodes: list<array{
     *         nsid: string,
     *         label: string,
     *         username: string|null,
     *         realname: string|null,
     *         is_root: bool,
     *         starred: bool,
     *         note_preview: string|null,
     *         child_count: int,
     *         photos_count: int
     *     }>,
     *     max_edge_id: int,
     *     done: bool,
     *     crawl_status: string|null
     * }
     */
    public function delta(
        Connection $connection,
        string $subjectNsid,
        int $sinceEdgeId,
        ?int $crawlRunId,
    ): array {
        $rootNsid = $connection->connection_key;
        $edges = [];

        if ($subjectNsid === $rootNsid) {
            foreach ($this->connectionContacts->nsidsForConnection($rootNsid) as $contactNsid) {
                $edges[] = [
                    'id' => 0,
                    'from' => $rootNsid,
                    'to' => $contactNsid,
                ];
            }
        } else {
            $edges = array_map(
                fn (array $edge): array => [
                    'id' => $edge['id'],
                    'from' => $edge['subject_nsid'],
                    'to' => $edge['contact_nsid'],
                ],
                $this->subjectContacts->listEdgesForSubjectSince($rootNsid, $subjectNsid, $sinceEdgeId),
            );
        }

        $newNodeNsids = [];
        foreach ($edges as $edge) {
            $newNodeNsids[] = $edge['to'];

            if ($edge['from'] !== $rootNsid) {
                $newNodeNsids[] = $edge['from'];
            }
        }

        $newNodeNsids = array_values(array_unique($newNodeNsids));
        $maxEdgeId = $sinceEdgeId;

        foreach ($edges as $edge) {
            if ($edge['id'] > $maxEdgeId) {
                $maxEdgeId = $edge['id'];
            }
        }

        if ($subjectNsid !== $rootNsid) {
            $storedMax = $this->subjectContacts->maxEdgeIdForSubject($rootNsid, $subjectNsid);
            if ($storedMax > $maxEdgeId) {
                $maxEdgeId = $storedMax;
            }
        }

        $crawlStatus = null;
        $done = true;

        if ($crawlRunId !== null) {
            $run = CrawlRun::query()
                ->where('connection_key', $rootNsid)
                ->whereKey($crawlRunId)
                ->first();

            if ($run instanceof CrawlRun) {
                $crawlStatus = $run->status instanceof CrawlRunStatus
                    ? $run->status->value
                    : (string) $run->status;
                $done = ! in_array($crawlStatus, ['running', 'pending'], true);
            }
        }

        return [
            'edges' => $edges,
            'nodes' => $this->buildNodes($connection, $rootNsid, $newNodeNsids),
            'max_edge_id' => $maxEdgeId,
            'done' => $done,
            'crawl_status' => $crawlStatus,
        ];
    }

    /**
     * @param  list<string>  $allDirectNsids
     * @return list<string>
     */
    private function selectDirectContactNsids(Connection $connection, array $allDirectNsids, int $directLimit): array
    {
        if ($allDirectNsids === [] || $directLimit === 0 || count($allDirectNsids) <= $directLimit) {
            return $allDirectNsids;
        }

        $connectionKey = $connection->connection_key;
        $starredSet = array_fill_keys(
            array_intersect(
                $this->annotationRecords->starredContactNsids($connectionKey),
                $allDirectNsids,
            ),
            true,
        );
        $starred = array_keys($starredSet);

        $photoCounts = $this->catalogCounts->forContacts($connection, $allDirectNsids);

        $rest = array_values(array_filter(
            $allDirectNsids,
            fn (string $nsid): bool => ! isset($starredSet[$nsid]),
        ));

        usort(
            $rest,
            function (string $left, string $right) use ($photoCounts): int {
                $leftPhotos = $photoCounts[$left]['photos'] ?? 0;
                $rightPhotos = $photoCounts[$right]['photos'] ?? 0;

                if ($leftPhotos !== $rightPhotos) {
                    return $rightPhotos <=> $leftPhotos;
                }

                return $left <=> $right;
            },
        );

        $remainingSlots = max(0, $directLimit - count($starred));

        return array_values(array_unique([
            ...$starred,
            ...array_slice($rest, 0, $remainingSlots),
        ]));
    }

    /**
     * @param  list<string>  $nodeNsids
     * @return list<array{
     *     nsid: string,
     *     label: string,
     *     username: string|null,
     *     realname: string|null,
     *     is_root: bool,
     *     starred: bool,
     *     note_preview: string|null,
     *     child_count: int,
     *     photos_count: int
     * }>
     */
    private function buildNodes(Connection $connection, string $rootNsid, array $nodeNsids): array
    {
        if ($nodeNsids === []) {
            return [];
        }

        $contactNsids = array_values(array_filter(
            $nodeNsids,
            fn (string $nsid): bool => $nsid !== $rootNsid,
        ));

        $contacts = $this->contacts->listByNsids($contactNsids);

        /** @var array<string, Contact> $contactMap */
        $contactMap = [];
        foreach ($contacts as $contact) {
            $contactMap[(string) $contact->getAttribute('nsid')] = $contact;
        }

        $annotationMap = $this->annotations->mapForContacts(
            $connection->connection_key,
            $contactNsids,
        );

        $childCounts = $this->childCounts($connection->connection_key, $rootNsid, $nodeNsids);
        $photoCounts = $this->catalogCounts->forContacts($connection, $contactNsids);

        $nodes = [];
        foreach ($nodeNsids as $nsid) {
            if ($nsid === $rootNsid) {
                $rootPhotoRow = $this->catalogCounts->forContacts($connection, [$rootNsid]);
                $rootPhotos = $rootPhotoRow[$rootNsid]['photos'] ?? 0;

                $nodes[] = [
                    'nsid' => $rootNsid,
                    'label' => $connection->username ?? $connection->fullname ?? 'Me',
                    'username' => $connection->username,
                    'realname' => $connection->fullname,
                    'is_root' => true,
                    'starred' => false,
                    'note_preview' => null,
                    'child_count' => $childCounts[$rootNsid] ?? 0,
                    'photos_count' => $rootPhotos,
                ];

                continue;
            }

            $contact = $contactMap[$nsid] ?? null;
            $annotation = $annotationMap[$nsid] ?? [
                'note' => null,
                'starred' => false,
                'note_preview' => null,
            ];

            $nodes[] = [
                'nsid' => $nsid,
                'label' => (string) ($contact?->getAttribute('realname') ?? $contact?->getAttribute('username') ?? $nsid),
                'username' => $contact?->getAttribute('username') !== null ? (string) $contact->getAttribute('username') : null,
                'realname' => $contact?->getAttribute('realname') !== null ? (string) $contact->getAttribute('realname') : null,
                'is_root' => false,
                'starred' => $annotation['starred'],
                'note_preview' => $annotation['note_preview'],
                'child_count' => $childCounts[$nsid] ?? 0,
                'photos_count' => $photoCounts[$nsid]['photos'] ?? 0,
            ];
        }

        return $nodes;
    }

    /**
     * @param  list<string>  $nodeNsids
     * @return array<string, int>
     */
    private function childCounts(string $connectionKey, string $rootNsid, array $nodeNsids): array
    {
        $counts = [];

        foreach ($nodeNsids as $nsid) {
            if ($nsid === $rootNsid) {
                $counts[$nsid] = $this->connectionContacts->countForConnection($connectionKey);
            } else {
                $counts[$nsid] = $this->subjectContacts->countForSubject($connectionKey, $nsid);
            }
        }

        return $counts;
    }
}
