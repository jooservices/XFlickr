<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class ShowSettingsRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['sometimes', 'nullable', 'string', 'in:general,flickr,storage,storages'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tab = (string) $this->query('tab', 'general');

        if (! in_array($tab, ['general', 'flickr', 'storage', 'storages'], true)) {
            $tab = 'general';
        }

        if ($tab === 'storages') {
            $tab = 'storage';
        }

        $this->merge(['tab' => $tab]);
    }

    public function tab(): string
    {
        return (string) $this->input('tab', 'general');
    }
}
