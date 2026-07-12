<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests\Api;

use App\Http\Requests\Request;

final class ShowFlickrRateLimitUsageRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'connection_key' => ['required', 'string'],
            'hours' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ];
    }

    public function connectionKey(): string
    {
        return (string) $this->validated('connection_key');
    }

    public function hours(): int
    {
        $hours = $this->validated('hours');

        return is_numeric($hours) ? (int) $hours : 24;
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->query(), $this->all());
    }
}
