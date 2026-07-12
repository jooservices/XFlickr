<?php

declare(strict_types=1);

namespace Modules\Storage\Http\Requests\Api\Storage;

use App\Http\Requests\Request;
use Modules\Storage\Http\Requests\Concerns\ResolvesStorageAccount;

final class DeleteStorageItemsRequest extends Request
{
    use ResolvesStorageAccount;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->storageAccountRules(),
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'string', 'min:1'],
            'container_id' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->storageAccountMessages(),
            'item_ids.required' => 'item_ids is required.',
            'item_ids.min' => 'Select at least one item to delete.',
        ];
    }

    /**
     * @return list<string>
     */
    public function itemIds(): array
    {
        $itemIds = $this->input('item_ids', []);

        if (! is_array($itemIds)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $id): string => is_string($id) || is_numeric($id) ? (string) $id : '', $itemIds),
            static fn (string $id): bool => $id !== '',
        ));
    }
}
