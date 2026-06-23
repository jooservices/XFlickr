<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Storage;

use App\Http\Requests\Request;

final class GooglePhotosThumbnailRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
            'media_id' => ['required', 'string'],
        ];
    }

    public function accountId(): int
    {
        return (int) $this->query('account_id', 0);
    }

    public function mediaId(): string
    {
        return (string) $this->query('media_id', '');
    }
}
