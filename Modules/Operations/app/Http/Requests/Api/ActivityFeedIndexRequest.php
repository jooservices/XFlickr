<?php

declare(strict_types=1);

namespace Modules\Operations\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Request;
use Modules\Operations\Dto\ActivityFeedFilter;

final class ActivityFeedIndexRequest extends Request
{
    use NormalizesPagination;

    protected function defaultPerPage(): int
    {
        return 15;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'type' => ['sometimes', 'nullable', 'string', 'in:domain,audit,system,security,activity'],
            'level' => ['sometimes', 'nullable', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'action_prefix' => ['sometimes', 'nullable', 'string', 'max:120'],
            'correlation_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function toFilter(): ActivityFeedFilter
    {
        return ActivityFeedFilter::fromArray([
            'type' => $this->query('type'),
            'level' => $this->query('level'),
            'action_prefix' => $this->query('action_prefix'),
            'correlation_id' => $this->query('correlation_id'),
            'from' => $this->query('from'),
            'to' => $this->query('to'),
            'page' => $this->page(),
            'per_page' => min(50, $this->perPage()),
        ]);
    }
}
