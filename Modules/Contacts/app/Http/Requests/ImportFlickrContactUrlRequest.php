<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCrawlTypes;
use App\Http\Requests\Request;

final class ImportFlickrContactUrlRequest extends Request
{
    use ResolvesCrawlTypes;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'url' => ['required', 'string', 'max:2048'],
            'start_crawl' => ['sometimes', 'boolean'],
        ], $this->crawlTypeRules());
    }

    /**
     * @return list<string>
     */
    protected function defaultCrawlTypes(): array
    {
        return ['photos', 'photosets', 'galleries', 'favorites'];
    }

    public function url(): string
    {
        return trim((string) $this->input('url', ''));
    }

    public function startCrawl(): bool
    {
        return $this->boolean('start_crawl', true);
    }
}
