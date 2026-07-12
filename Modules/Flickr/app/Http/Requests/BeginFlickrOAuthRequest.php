<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests;

use App\Http\Requests\Request;

final class BeginFlickrOAuthRequest extends Request
{
    protected function prepareForValidation(): void
    {
        $appProfile = $this->query('app_profile');

        if (! is_string($appProfile) || trim($appProfile) === '') {
            $this->merge(['app_profile' => 'main']);

            return;
        }

        $this->merge(['app_profile' => trim($appProfile)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_profile' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function appProfile(): string
    {
        return (string) $this->validated('app_profile', 'main');
    }
}
