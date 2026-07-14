<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests\Api\Catalog;

use App\Http\Requests\Request;

final class PhotoDownloadProgressRequest extends Request
{
    public const MAX_IDS = 200;

    protected function prepareForValidation(): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            trim(...),
            explode(',', (string) $this->query('ids', '')),
        ))));

        $this->merge(['ids' => array_slice($ids, 0, self::MAX_IDS)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['sometimes', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['string', 'max:64'],
        ];
    }

    /**
     * @return list<string>
     */
    public function flickrPhotoIds(): array
    {
        $ids = $this->input('ids', []);

        return is_array($ids) ? array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )) : [];
    }
}
