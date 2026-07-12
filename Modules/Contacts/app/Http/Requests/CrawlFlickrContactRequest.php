<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Concerns\ResolvesCrawlTypes;
use App\Http\Requests\Request;

final class CrawlFlickrContactRequest extends Request
{
    use ResolvesCrawlTypes;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->crawlTypeRules();
    }

    /**
     * @return list<string>
     */
    protected function defaultCrawlTypes(): array
    {
        return ['photos', 'photosets', 'galleries', 'favorites'];
    }
}
