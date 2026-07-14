<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Concerns\ResolvesBulkSelectAll;
use App\Http\Requests\Request;

final class QueuePhotoDownloadRequest extends Request
{
    use ResolvesBulkSelectAll;

    protected function prepareForValidation(): void
    {
        $this->prepareBulkSelectAllForValidation();

        $contactNsids = $this->input('contact_nsids');

        if (is_string($contactNsids)) {
            $this->merge(['contact_nsids' => [$contactNsids]]);
        }

        $flickrPhotoIds = $this->input('flickr_photo_ids');

        if (is_string($flickrPhotoIds)) {
            $this->merge(['flickr_photo_ids' => [$flickrPhotoIds]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->bulkSelectAllRules(),
            'flickr_photo_id' => ['sometimes', 'nullable', 'string'],
            'flickr_photo_ids' => ['sometimes', 'nullable', 'array'],
            'flickr_photo_ids.*' => ['string'],
            'contact_nsid' => ['sometimes', 'nullable', 'string'],
            'contact_nsids' => ['sometimes', 'nullable', 'array'],
            'contact_nsids.*' => ['string'],
        ];
    }

    public function singlePhotoId(): ?string
    {
        $flickrPhotoId = $this->input('flickr_photo_id');

        return is_string($flickrPhotoId) && $flickrPhotoId !== '' ? $flickrPhotoId : null;
    }

    /**
     * @return list<string>
     */
    public function flickrPhotoIds(): array
    {
        $flickrPhotoIds = $this->input('flickr_photo_ids', []);

        return array_values(array_unique(array_filter(
            is_array($flickrPhotoIds) ? $flickrPhotoIds : [],
            static fn (mixed $photoId): bool => is_string($photoId) && $photoId !== '',
        )));
    }

    /**
     * @return list<string>
     */
    public function contactNsids(): array
    {
        $contactNsids = $this->input('contact_nsids', []);

        return array_values(array_filter(
            is_array($contactNsids) ? $contactNsids : [],
            static fn (mixed $nsid): bool => is_string($nsid) && $nsid !== '',
        ));
    }

    public function singleContactNsid(): ?string
    {
        $contactNsid = $this->input('contact_nsid');

        return is_string($contactNsid) && $contactNsid !== '' ? $contactNsid : null;
    }
}
