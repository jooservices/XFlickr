<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use App\Http\Requests\Request;

final class FlickrContactProgressRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $nsids = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $this->query('nsids', '')),
        )));

        $this->merge(['nsids' => $nsids]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nsids' => ['sometimes', 'array'],
            'nsids.*' => ['string'],
        ];
    }

    /**
     * @return list<string>
     */
    public function nsidList(): array
    {
        $nsids = $this->input('nsids', []);

        return is_array($nsids) ? $nsids : [];
    }
}
