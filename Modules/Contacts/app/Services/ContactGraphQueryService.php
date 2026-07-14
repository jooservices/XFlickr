<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\ContactQueryRepository;
use App\Repositories\Crawler\CrawlRunQueryRepository;
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
        private readonly ContactStatsService $stats,
        private readonly ContactGraphRuntimeConfig $graphConfig,
        private readonly CrawlRunQueryRepository $crawlRuns,
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
        $edges = $this->buildDeltaEdges($rootNsid, $subjectNsid, $sinceEdgeId);
        $newNodeNsids = $this->nodeNsidsFromEdges($edges, $rootNsid);
        $maxEdgeId = $this->resolveMaxEdgeId($rootNsid, $subjectNsid, $sinceEdgeId, $edges);
        $crawlCompletion = $this->resolveCrawlCompletion($rootNsid, $crawlRunId);

        return [
            'edges' => $edges,
            'nodes' => $this->buildNodes($connection, $rootNsid, $newNodeNsids),
            'max_edge_id' => $maxEdgeId,
            'done' => $crawlCompletion['done'],
            'crawl_status' => $crawlCompletion['crawl_status'],
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

        $photoCounts = $this->stats->catalogCountsFor($connection, $allDirectNsids);

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
     * @return list<array{id: int, from: string, to: string}>
     */
    private function buildDeltaEdges(string $rootNsid, string $subjectNsid, int $sinceEdgeId): array
    {
        if ($subjectNsid === $rootNsid) {
            $edges = [];
            foreach ($this->connectionContacts->nsidsForConnection($rootNsid) as $contactNsid) {
                $edges[] = [
                    'id' => 0,
                    'from' => $rootNsid,
                    'to' => $contactNsid,
                ];
            }

            return $edges;
        }

        return array_map(
            fn (array $edge): array => [
                'id' => $edge['id'],
                'from' => $edge['subject_nsid'],
                'to' => $edge['contact_nsid'],
            ],
            $this->subjectContacts->listEdgesForSubjectSince($rootNsid, $subjectNsid, $sinceEdgeId),
        );
    }

    /**
     * @param  list<array{id: int, from: string, to: string}>  $edges
     * @return list<string>
     */
    private function nodeNsidsFromEdges(array $edges, string $rootNsid): array
    {
        $newNodeNsids = [];
        foreach ($edges as $edge) {
            $newNodeNsids[] = $edge['to'];

            if ($edge['from'] !== $rootNsid) {
                $newNodeNsids[] = $edge['from'];
            }
        }

        return array_values(array_unique($newNodeNsids));
    }

    /**
     * @param  list<array{id: int, from: string, to: string}>  $edges
     */
    private function resolveMaxEdgeId(string $rootNsid, string $subjectNsid, int $sinceEdgeId, array $edges): int
    {
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

        return $maxEdgeId;
    }

    /**
     * @return array{done: bool, crawl_status: string|null}
     */
    private function resolveCrawlCompletion(string $rootNsid, ?int $crawlRunId): array
    {
        if ($crawlRunId === null) {
            return ['done' => true, 'crawl_status' => null];
        }

        $run = $this->crawlRuns->findForConnection($rootNsid, $crawlRunId);

        if (! $run instanceof CrawlRun) {
            return ['done' => true, 'crawl_status' => null];
        }

        $crawlStatus = $run->status instanceof CrawlRunStatus
            ? $run->status->value
            : (string) $run->status;

        return [
            'done' => ! in_array($crawlStatus, ['running', 'pending'], true),
            'crawl_status' => $crawlStatus,
        ];
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

        $context = $this->loadNodeBuildContext($connection, $rootNsid, $nodeNsids);

        $nodes = [];
        foreach ($nodeNsids as $nsid) {
            if ($nsid === $rootNsid) {
                $nodes[] = $this->mapRootNodeRow($connection, $rootNsid, $context);

                continue;
            }

            $nodes[] = $this->mapContactNodeRow(
                $nsid,
                $context['contactMap'][$nsid] ?? null,
                $context['annotationMap'][$nsid] ?? [
                    'note' => null,
                    'starred' => false,
                    'note_preview' => null,
                ],
                $context['childCounts'][$nsid] ?? 0,
                $context['photoCounts'][$nsid]['photos'] ?? 0,
            );
        }

        return $nodes;
    }

    /**
     * @param  list<string>  $nodeNsids
     * @return array{
     *     contactMap: array<string, Contact>,
     *     annotationMap: array<string, array{note: mixed, starred: bool, note_preview: string|null}>,
     *     childCounts: array<string, int>,
     *     photoCounts: array<string, array{photos: int}>
     * }
     */
    private function loadNodeBuildContext(Connection $connection, string $rootNsid, array $nodeNsids): array
    {
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

        return [
            'contactMap' => $contactMap,
            'annotationMap' => $this->annotations->mapForContacts(
                $connection->connection_key,
                $contactNsids,
            ),
            'childCounts' => $this->childCounts($connection->connection_key, $rootNsid, $nodeNsids),
            'photoCounts' => $this->stats->catalogCountsFor($connection, $contactNsids),
        ];
    }

    /**
     * @param  array{
     *     contactMap: array<string, Contact>,
     *     annotationMap: array<string, array{note: mixed, starred: bool, note_preview: string|null}>,
     *     childCounts: array<string, int>,
     *     photoCounts: array<string, array{photos: int}>
     * }  $context
     * @return array{
     *     nsid: string,
     *     label: string,
     *     username: string|null,
     *     realname: string|null,
     *     is_root: bool,
     *     starred: bool,
     *     note_preview: string|null,
     *     child_count: int,
     *     photos_count: int
     * }
     */
    private function mapRootNodeRow(Connection $connection, string $rootNsid, array $context): array
    {
        $rootPhotoRow = $this->stats->catalogCountsFor($connection, [$rootNsid]);
        $rootPhotos = $rootPhotoRow[$rootNsid]['photos'] ?? 0;

        return [
            'nsid' => $rootNsid,
            'label' => $connection->username ?? $connection->fullname ?? 'Me',
            'username' => $connection->username,
            'realname' => $connection->fullname,
            'is_root' => true,
            'starred' => false,
            'note_preview' => null,
            'child_count' => $context['childCounts'][$rootNsid] ?? 0,
            'photos_count' => $rootPhotos,
        ];
    }

    /**
     * @param  array{note: mixed, starred: bool, note_preview: string|null}  $annotation
     * @return array{
     *     nsid: string,
     *     label: string,
     *     username: string|null,
     *     realname: string|null,
     *     is_root: bool,
     *     starred: bool,
     *     note_preview: string|null,
     *     child_count: int,
     *     photos_count: int
     * }
     */
    private function mapContactNodeRow(
        string $nsid,
        ?Contact $contact,
        array $annotation,
        int $childCount,
        int $photosCount,
    ): array {
        return [
            'nsid' => $nsid,
            'label' => (string) ($contact?->getAttribute('realname') ?? $contact?->getAttribute('username') ?? $nsid),
            'username' => $contact?->getAttribute('username') !== null ? (string) $contact->getAttribute('username') : null,
            'realname' => $contact?->getAttribute('realname') !== null ? (string) $contact->getAttribute('realname') : null,
            'is_root' => false,
            'starred' => $annotation['starred'],
            'note_preview' => $annotation['note_preview'],
            'child_count' => $childCount,
            'photos_count' => $photosCount,
        ];
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
