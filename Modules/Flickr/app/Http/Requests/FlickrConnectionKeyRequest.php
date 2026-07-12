<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests;

use App\Http\Requests\Request;

final class FlickrConnectionKeyRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_key' => ['required', 'string'],
        ];
    }

    public function connectionKey(): string
    {
        return (string) $this->validated('connection_key');
    }
}
