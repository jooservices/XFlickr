<?php

declare(strict_types=1);

namespace App\Http\Requests\Flickr;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Request;
use App\Services\Flickr\ContactListSorter;
use App\Support\Query\QuerySorter;

final class ListFlickrContactsRequest extends Request
{
    use NormalizesPagination;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->query('search', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'search' => ['sometimes', 'nullable', 'string'],
            'sort' => ['sometimes', 'nullable', 'string'],
            'direction' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function defaultSort(): string
    {
        return 'username';
    }

    protected function defaultDirection(): string
    {
        return 'asc';
    }

    public function search(): string
    {
        return trim((string) $this->input('search', ''));
    }

    public function sort(): string
    {
        return app(QuerySorter::class)->resolveSort(
            (string) $this->input('sort', $this->defaultSort()),
            ContactListSorter::SORTABLE_COLUMNS,
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

    public function rawSort(): string
    {
        return (string) $this->input('sort', $this->defaultSort());
    }

    public function rawDirection(): string
    {
        return (string) $this->input('direction', $this->defaultDirection());
    }
}
