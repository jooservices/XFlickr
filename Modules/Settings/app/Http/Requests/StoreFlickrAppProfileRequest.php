<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use App\Http\Requests\Request;
use Modules\Flickr\Dto\FlickrAppProfileDto;

final class StoreFlickrAppProfileRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'label' => ['nullable', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'api_secret' => ['required', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function toDto(): FlickrAppProfileDto
    {
        return FlickrAppProfileDto::from($this->validated());
    }
}
