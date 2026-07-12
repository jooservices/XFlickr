<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Request;

final class ListCrawlLogsRequest extends Request
{
    use NormalizesPagination;

    protected function defaultPerPage(): int
    {
        return 50;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
