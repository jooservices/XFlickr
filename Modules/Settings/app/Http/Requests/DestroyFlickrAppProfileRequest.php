<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;

final class DestroyFlickrAppProfileRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $profile = $this->route('profile');

        $this->merge([
            'profile' => is_string($profile) ? trim(urldecode($profile)) : $profile,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
        ];
    }

    public function profile(): string
    {
        return (string) $this->validated('profile');
    }
}
