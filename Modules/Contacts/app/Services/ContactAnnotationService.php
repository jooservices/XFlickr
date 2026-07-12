<?php

declare(strict_types=1);

namespace Modules\Contacts\Services;

use App\Repositories\Crawler\ConnectionContactQueryRepository;
use App\Repositories\Crawler\SubjectContactQueryRepository;
use Modules\Contacts\Models\ContactAnnotation;
use Modules\Contacts\Repositories\ContactAnnotationRepository;

final class ContactAnnotationService
{
    public function __construct(
        private readonly ContactAnnotationRepository $annotations,
        private readonly ConnectionContactQueryRepository $connectionContacts,
        private readonly SubjectContactQueryRepository $subjectContacts,
    ) {}

    /**
     * @return array{nsid: string, note: string|null, starred: bool, starred_at: string|null}
     */
    public function forContact(string $connectionKey, string $contactNsid): array
    {
        return $this->serialize($contactNsid, $this->annotations->findForContact($connectionKey, $contactNsid));
    }

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, array{note: string|null, starred: bool, note_preview: string|null}>
     */
    public function mapForContacts(string $connectionKey, array $contactNsids): array
    {
        $rows = $this->annotations->mapForContacts($connectionKey, $contactNsids);
        $mapped = [];

        foreach ($contactNsids as $contactNsid) {
            $mapped[$contactNsid] = $this->serializeSummary($rows[$contactNsid] ?? null);
        }

        return $mapped;
    }

    public function update(
        string $connectionKey,
        string $contactNsid,
        ?string $note,
        ?bool $starred,
    ): ContactAnnotation {
        $this->assertAnnotatable($connectionKey, $contactNsid);

        return $this->annotations->upsertForContact($connectionKey, $contactNsid, $note, $starred);
    }

    private function assertAnnotatable(string $connectionKey, string $contactNsid): void
    {
        if ($this->connectionContacts->existsForConnection($connectionKey, $contactNsid)) {
            return;
        }

        if ($this->subjectContacts->existsInNetwork($connectionKey, $contactNsid)) {
            return;
        }

        abort(404, 'Contact is not known for this account.');
    }

    /**
     * @return array{nsid: string, note: string|null, starred: bool, starred_at: string|null}
     */
    private function serialize(string $contactNsid, ?ContactAnnotation $annotation): array
    {
        return [
            'nsid' => $contactNsid,
            'note' => $annotation?->note,
            'starred' => $annotation?->isStarred() ?? false,
            'starred_at' => $annotation?->starred_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{note: string|null, starred: bool, note_preview: string|null}
     */
    private function serializeSummary(?ContactAnnotation $annotation): array
    {
        $note = $annotation?->note;

        return [
            'note' => $note,
            'starred' => $annotation?->isStarred() ?? false,
            'note_preview' => $this->notePreview($note),
        ];
    }

    private function notePreview(?string $note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }

        if (strlen($note) <= 80) {
            return $note;
        }

        return substr($note, 0, 77).'...';
    }
}
