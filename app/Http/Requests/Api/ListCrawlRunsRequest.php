<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Concerns\NormalizesSorting;
use App\Http\Requests\Request;

final class ListCrawlRunsRequest extends Request
{
    use NormalizesPagination;
    use NormalizesSorting;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            ...$this->sortingRules(),
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'crawl_type', 'subject_nsid', 'status', 'photos_discovered', 'api_calls', 'started_at'];
    }
}
