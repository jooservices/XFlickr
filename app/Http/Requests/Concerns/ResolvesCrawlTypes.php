<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;
use JOOservices\XFlickrCrawler\Enums\CrawlType;

trait ResolvesCrawlTypes
{
    /**
     * @return list<string>
     */
    abstract protected function defaultCrawlTypes(): array;

    protected function prepareCrawlTypesForValidation(): void
    {
        $types = $this->input('types');

        if ($types === null) {
            $this->merge(['types' => $this->defaultCrawlTypes()]);

            return;
        }

        if (! is_array($types)) {
            $this->merge(['types' => [$types]]);
        }
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCrawlTypesForValidation();
    }

    /**
     * @return array<string, mixed>
     */
    protected function crawlTypeRules(): array
    {
        return [
            'types' => ['sometimes', 'array'],
            'types.*' => ['required', 'string', Rule::in(array_map(
                fn (CrawlType $type): string => $type->value,
                CrawlType::cases(),
            ))],
        ];
    }

    /**
     * @return list<CrawlType>
     */
    public function crawlTypes(): array
    {
        $types = $this->input('types', $this->defaultCrawlTypes());
        if (! is_array($types)) {
            $types = [$types];
        }

        return array_map(
            fn (mixed $type): CrawlType => CrawlType::from((string) $type),
            $types,
        );
    }
}
