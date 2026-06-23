<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Http\Requests\Concerns\NormalizesSorting;
use App\Http\Requests\Request;

final class ListTransferBatchesRequest extends Request
{
    use NormalizesSorting;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->sortingRules(),
            'status' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedSorts(): array
    {
        return ['id', 'type', 'subject_nsid', 'status', 'total_count', 'completed_count', 'failed_count', 'created_at'];
    }

    public function status(): ?string
    {
        $status = $this->query('status');

        return is_string($status) && $status !== '' ? $status : null;
    }

    public function type(): ?string
    {
        $type = $this->query('type');

        return is_string($type) && $type !== '' ? $type : null;
    }

    public function isActive(): bool
    {
        return $this->boolean('active');
    }

    public function limit(): int
    {
        return min(50, max(1, (int) $this->query('limit', 20)));
    }
}
