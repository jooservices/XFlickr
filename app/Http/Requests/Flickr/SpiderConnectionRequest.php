<?php

declare(strict_types=1);

namespace App\Http\Requests\Flickr;

use Illuminate\Foundation\Http\FormRequest;

final class SpiderConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
