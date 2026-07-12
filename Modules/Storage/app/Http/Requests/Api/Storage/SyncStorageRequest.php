<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Requests\Api\Storage;

use App\Http\Requests\Request;
use Modules\Storage\Http\Requests\Concerns\ResolvesStorageAccount;

final class SyncStorageRequest extends Request
{
    use ResolvesStorageAccount;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->storageAccountRules(),
            'max_batches' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'container_id' => ['sometimes', 'nullable', 'string'],
            'reconcile' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->storageAccountMessages();
    }

    public function maxBatches(): int
    {
        return min(20, max(1, (int) $this->input('max_batches', 3)));
    }

    public function shouldReconcile(): bool
    {
        return $this->boolean('reconcile');
    }
}
