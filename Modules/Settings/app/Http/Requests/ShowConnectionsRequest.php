<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class ShowConnectionsRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'nullable', 'string', 'in:flickr,storage'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $provider = (string) $this->query('provider', 'flickr');

        if (! in_array($provider, ['flickr', 'storage'], true)) {
            $provider = 'flickr';
        }

        $this->merge([
            'provider' => $provider,
        ]);
    }

    public function provider(): string
    {
        return (string) $this->input('provider', 'flickr');
    }
}
