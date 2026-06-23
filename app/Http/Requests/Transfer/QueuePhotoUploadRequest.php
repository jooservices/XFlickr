<?php

declare(strict_types=1);

namespace App\Http\Requests\Transfer;

use App\Http\Requests\Request;
use App\Models\StorageAccount;
use App\Repositories\StorageAccountRepository;

final class QueuePhotoUploadRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $contactNsids = $this->input('contact_nsids');

        if (is_string($contactNsids)) {
            $this->merge(['contact_nsids' => [$contactNsids]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'storage_account_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'flickr_photo_id' => ['sometimes', 'nullable', 'string'],
            'contact_nsid' => ['sometimes', 'nullable', 'string'],
            'contact_nsids' => ['sometimes', 'nullable', 'array'],
            'contact_nsids.*' => ['string'],
        ];
    }

    public function resolvedStorageAccount(): ?StorageAccount
    {
        $storageAccountId = (int) $this->input('storage_account_id', 0);
        $accounts = app(StorageAccountRepository::class);

        if ($storageAccountId > 0) {
            return $accounts->findByIdOrFail($storageAccountId);
        }

        return $accounts->findDefault();
    }

    public function singlePhotoId(): ?string
    {
        $flickrPhotoId = $this->input('flickr_photo_id');

        return is_string($flickrPhotoId) && $flickrPhotoId !== '' ? $flickrPhotoId : null;
    }

    public function singleContactNsid(): ?string
    {
        $contactNsid = $this->input('contact_nsid');

        return is_string($contactNsid) && $contactNsid !== '' ? $contactNsid : null;
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
}
