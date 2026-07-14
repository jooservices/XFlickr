<?php

declare(strict_types=1);

namespace Modules\Contacts\Repositories;

use Illuminate\Support\Collection;
use Jooservices\LaravelRepository\Repositories\EloquentRepository;
use Jooservices\LaravelRepository\Traits\HasCrud;
use Modules\Contacts\Models\ContactAnnotation;

/**
 * @extends EloquentRepository<ContactAnnotation>
 */
final class ContactAnnotationRepository extends EloquentRepository
{
    use HasCrud;

    public function __construct(ContactAnnotation $model)
    {
        parent::__construct($model);
    }

    public function findForContact(string $connectionKey, string $contactNsid): ?ContactAnnotation
    {
        $annotation = $this->newQuery()
            ->forConnection($connectionKey)
            ->where('contact_nsid', $contactNsid)
            ->first();

        return $annotation instanceof ContactAnnotation ? $annotation : null;
    }

    /**
     * @param  list<string>  $contactNsids
     * @return array<string, ContactAnnotation>
     */
    public function mapForContacts(string $connectionKey, array $contactNsids): array
    {
        if ($contactNsids === []) {
            return [];
        }

        /** @var Collection<int, ContactAnnotation> $rows */
        $rows = $this->newQuery()
            ->forConnection($connectionKey)
            ->whereIn('contact_nsid', $contactNsids)
            ->get();

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row->contact_nsid] = $row;
        }

        return $mapped;
    }

    public function upsertForContact(
        string $connectionKey,
        string $contactNsid,
        ?string $note,
        ?bool $starred,
    ): ContactAnnotation {
        /** @var ContactAnnotation $annotation */
        $annotation = $this->newQuery()->firstOrNew([
            'connection_key' => $connectionKey,
            'contact_nsid' => $contactNsid,
        ]);

        if ($note !== null) {
            $trimmed = trim($note);
            $annotation->note = $trimmed === '' ? null : $trimmed;
        }

        if ($starred !== null) {
            $annotation->starred_at = $starred ? ($annotation->starred_at ?? now()) : null;
        }

        $annotation->save();

        return $annotation->fresh() ?? $annotation;
    }

    /**
     * @return list<string>
     */
    public function starredContactNsids(string $connectionKey): array
    {
        return $this->newQuery()
            ->forConnection($connectionKey)
            ->whereNotNull('starred_at')
            ->pluck('contact_nsid')
            ->map(fn (mixed $nsid): string => (string) $nsid)
            ->all();
    }
}
