<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests\Api;

use App\Http\Requests\Request;

final class ContactGraphDeltaRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_nsid' => ['required', 'string'],
            'since_edge_id' => ['sometimes', 'integer', 'min:0'],
            'crawl_run_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function subjectNsid(): string
    {
        return (string) $this->query('subject_nsid');
    }

    public function sinceEdgeId(): int
    {
        return max(0, (int) $this->query('since_edge_id', 0));
    }

    public function crawlRunId(): ?int
    {
        $value = $this->query('crawl_run_id');

        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }
}
