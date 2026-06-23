<?php

declare(strict_types=1);

namespace App\Http\Requests\Flickr;

use App\Http\Requests\Concerns\ResolvesCrawlTypes;
use App\Http\Requests\Request;

final class CrawlFlickrContactBulkRequest extends Request
{
    use ResolvesCrawlTypes;

    protected function prepareForValidation(): void
    {
        $this->prepareCrawlTypesForValidation();

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
            ...$this->crawlTypeRules(),
            'contact_nsids' => ['sometimes', 'array'],
            'contact_nsids.*' => ['string'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function defaultCrawlTypes(): array
    {
        return ['photos', 'photosets', 'galleries', 'favorites'];
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
