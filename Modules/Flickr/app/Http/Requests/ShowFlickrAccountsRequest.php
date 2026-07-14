<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests;

use App\Http\Requests\Request;

final class ShowFlickrAccountsRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
