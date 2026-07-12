<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Api\Catalog;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Concerns\NormalizesSorting;
use App\Http\Requests\Request;

abstract class CatalogListRequest extends Request
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
            ...$this->filterRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'owner_nsid' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function ownerNsid(): ?string
    {
        $ownerNsid = $this->query('owner_nsid');

        return is_string($ownerNsid) && $ownerNsid !== '' ? $ownerNsid : null;
    }
}
