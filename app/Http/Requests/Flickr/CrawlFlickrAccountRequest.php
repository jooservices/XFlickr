<?php

declare(strict_types=1);

namespace App\Http\Requests\Flickr;

use App\Http\Requests\Concerns\ResolvesCrawlTypes;
use App\Http\Requests\Request;

final class CrawlFlickrAccountRequest extends Request
{
    use ResolvesCrawlTypes;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->crawlTypeRules(),
            'subject_nsid' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function defaultCrawlTypes(): array
    {
        return ['contacts', 'photos', 'photosets', 'galleries', 'favorites'];
    }

    public function subjectNsid(): ?string
    {
        $subjectNsid = $this->input('subject_nsid');

        return is_string($subjectNsid) && $subjectNsid !== '' ? $subjectNsid : null;
    }
}
