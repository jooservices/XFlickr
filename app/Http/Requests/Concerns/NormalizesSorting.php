<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Query\QuerySorter;

trait NormalizesSorting
{
    protected function defaultSort(): string
    {
        return 'id';
    }

    protected function defaultDirection(): string
    {
        return 'desc';
    }

    /**
     * @return list<string>
     */
    abstract protected function allowedSorts(): array;

    /**
     * @return array<string, mixed>
     */
    protected function sortingRules(): array
    {
        return [
            'sort' => ['sometimes', 'nullable', 'string'],
            'direction' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function sort(): string
    {
        return app(QuerySorter::class)->resolveSort(
            (string) $this->input('sort', $this->defaultSort()),
            $this->allowedSorts(),
            $this->defaultSort(),
        );
    }

    public function direction(): string
    {
        return app(QuerySorter::class)->resolveDirection(
            (string) $this->input('direction', $this->defaultDirection()),
            $this->defaultDirection(),
        );
    }
}
