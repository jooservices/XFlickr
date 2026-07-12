<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Request;
use App\Support\Query\QuerySorter;
use Modules\Contacts\Services\ContactListSorter;

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
            'starred_only' => ['sometimes', 'boolean'],
            'view' => ['sometimes', 'nullable', 'string', 'in:table,graph'],
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

    public function starredOnly(): bool
    {
        return $this->boolean('starred_only');
    }

    public function viewMode(): string
    {
        $view = (string) $this->input('view', 'table');

        return in_array($view, ['table', 'graph'], true) ? $view : 'table';
    }
}
