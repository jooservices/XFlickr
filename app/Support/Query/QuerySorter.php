<?php

declare(strict_types=1);

namespace App\Support\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class QuerySorter
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $allowedColumns
     * @return Builder<TModel>
     */
    public function apply(
        Builder $query,
        string $sort,
        string $direction,
        array $allowedColumns,
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
    ): Builder {
        return $query->orderBy(
            $this->resolveSort($sort, $allowedColumns, $defaultSort),
            $this->resolveDirection($direction, $defaultDirection),
        );
    }

    /**
     * @param  list<string>  $allowedColumns
     */
    public function resolveSort(string $sort, array $allowedColumns, string $defaultSort = 'id'): string
    {
        return in_array($sort, $allowedColumns, true) ? $sort : $defaultSort;
    }

    public function resolveDirection(string $direction, string $defaultDirection = 'desc'): string
    {
        $normalized = strtolower($direction);

        if ($normalized === 'asc' || $normalized === 'desc') {
            return $normalized;
        }

        return strtolower($defaultDirection) === 'asc' ? 'asc' : 'desc';
    }
}
