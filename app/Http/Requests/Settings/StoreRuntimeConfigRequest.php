<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Request;

final class StoreRuntimeConfigRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:string,int,float,bool,array,json,null'],
            'value' => ['nullable'],
        ];
    }
}
