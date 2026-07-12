<?php

declare(strict_types=1);

namespace App\Repositories\Crawler;

use Illuminate\Support\Collection;
use JOOservices\XFlickrCrawler\Models\SubjectContact;

final class SubjectContactQueryRepository
{
    /**
     * @return list<array{id: int, subject_nsid: string, contact_nsid: string}>
     */
    public function listEdgesForConnection(string $connectionKey): array
    {
        return SubjectContact::query()
            ->where('connection_key', $connectionKey)
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
     * @return list<array{id: int, subject_nsid: string, contact_nsid: string}>
     */
    public function listEdgesForSubjectSince(string $connectionKey, string $subjectNsid, int $sinceId): array
    {
        return SubjectContact::query()
            ->where('connection_key', $connectionKey)
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
            ->where('connection_key', $connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->count();
    }

    /**
     * @return Collection<int, string>
     */
    public function contactNsidsForSubject(string $connectionKey, string $subjectNsid): Collection
    {
        return SubjectContact::query()
            ->where('connection_key', $connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->orderBy('id')
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid);
    }

    public function maxEdgeIdForSubject(string $connectionKey, string $subjectNsid): int
    {
        $id = SubjectContact::query()
            ->where('connection_key', $connectionKey)
            ->where('subject_nsid', $subjectNsid)
            ->max('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    public function existsInNetwork(string $connectionKey, string $contactNsid): bool
    {
        return SubjectContact::query()
            ->where('connection_key', $connectionKey)
            ->where(function ($query) use ($contactNsid): void {
                $query
                    ->where('subject_nsid', $contactNsid)
                    ->orWhere('contact_nsid', $contactNsid);
            })
            ->exists();
    }
}
