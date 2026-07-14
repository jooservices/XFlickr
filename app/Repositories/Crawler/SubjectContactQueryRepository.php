<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Support\Collection;
use Modules\Crawler\Models\SubjectContact;

final class SubjectContactQueryRepository
{
    /**
     * @return list<array{id: int, subject_nsid: string, contact_nsid: string}>
     */
    public function listEdgesForConnection(string $connectionKey): array
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->orderBy('id')
            ->get(['id', 'subject_nsid', 'contact_nsid'])
            ->map(fn (SubjectContact $row): array => [
                'id' => (int) $row->getAttribute('id'),
                'subject_nsid' => (string) $row->getAttribute('subject_nsid'),
                'contact_nsid' => (string) $row->getAttribute('contact_nsid'),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $subjectNsids
     * @return list<array{id: int, subject_nsid: string, contact_nsid: string}>
     */
    public function listEdgesForSubjects(
        string $connectionKey,
        array $subjectNsids,
        ?int $maxPerSubject = null,
        ?int $maxTotal = null,
    ): array {
        if ($subjectNsids === []) {
            return [];
        }

        $edges = [];
        $total = 0;

        foreach ($subjectNsids as $subjectNsid) {
            if ($maxTotal !== null && $total >= $maxTotal) {
                break;
            }

            $remaining = $maxTotal !== null ? $maxTotal - $total : null;
            $limit = $maxPerSubject;
            if ($remaining !== null) {
                $limit = $limit === null ? $remaining : min($limit, $remaining);
            }

            $query = SubjectContact::query()
                ->forConnection($connectionKey)
                ->where('subject_nsid', $subjectNsid)
                ->orderBy('id')
                ->select(['id', 'subject_nsid', 'contact_nsid']);

            if ($limit !== null) {
                $query->limit(max(0, $limit));
            }

            foreach ($query->get() as $row) {
                $edges[] = [
                    'id' => (int) $row->getAttribute('id'),
                    'subject_nsid' => (string) $row->getAttribute('subject_nsid'),
                    'contact_nsid' => (string) $row->getAttribute('contact_nsid'),
                ];
                $total++;

                if ($maxTotal !== null && $total >= $maxTotal) {
                    break 2;
                }
            }
        }

        return $edges;
    }

    public function countForConnection(string $connectionKey): int
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->count();
    }

    /**
     * @param  list<string>  $subjectNsids
     * @return array<string, int>
     */
    public function countsGroupedBySubjects(string $connectionKey, array $subjectNsids): array
    {
        if ($subjectNsids === []) {
            return [];
        }

        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->whereIn('subject_nsid', $subjectNsids)
            ->groupBy('subject_nsid')
            ->selectRaw('subject_nsid, count(*) as aggregate')
            ->pluck('aggregate', 'subject_nsid')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @return list<array{id: int, subject_nsid: string, contact_nsid: string}>
     */
    public function listEdgesForSubjectSince(string $connectionKey, string $subjectNsid, int $sinceId): array
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->get(['id', 'subject_nsid', 'contact_nsid'])
            ->map(fn (SubjectContact $row): array => [
                'id' => (int) $row->getAttribute('id'),
                'subject_nsid' => (string) $row->getAttribute('subject_nsid'),
                'contact_nsid' => (string) $row->getAttribute('contact_nsid'),
            ])
            ->all();
    }

    public function countForSubject(string $connectionKey, string $subjectNsid): int
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->count();
    }

    /**
     * @return Collection<int, string>
     */
    public function contactNsidsForSubject(string $connectionKey, string $subjectNsid): Collection
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->orderBy('id')
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid);
    }

    public function maxEdgeIdForSubject(string $connectionKey, string $subjectNsid): int
    {
        $id = SubjectContact::query()
            ->forConnection($connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->max('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    public function existsInNetwork(string $connectionKey, string $contactNsid): bool
    {
        return SubjectContact::query()
            ->forConnection($connectionKey)
            ->where(function ($query) use ($contactNsid): void {
                $query
                    ->where('subject_nsid', $contactNsid)
                    ->orWhere('contact_nsid', $contactNsid);
            })
            ->exists();
    }
}
