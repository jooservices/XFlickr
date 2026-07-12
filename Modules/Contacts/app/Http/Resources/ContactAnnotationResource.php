<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Contacts\Models\ContactAnnotation;

/**
 * @mixin ContactAnnotation
 */
final class ContactAnnotationResource extends JsonResource
{
    public function __construct(
        private readonly string $contactNsid,
        mixed $resource,
    ) {
        parent::__construct($resource);
    }

    public static function from(string $contactNsid, ?ContactAnnotation $annotation): self
    {
        return new self($contactNsid, $annotation);
    }

    /**
     * @return array{nsid: string, note: string|null, starred: bool, starred_at: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var ContactAnnotation|null $annotation */
        $annotation = $this->resource;

        return [
            'nsid' => $this->contactNsid,
            'note' => $annotation?->note,
            'starred' => $annotation?->isStarred() ?? false,
            'starred_at' => $annotation?->starred_at?->toIso8601String(),
        ];
    }
}
