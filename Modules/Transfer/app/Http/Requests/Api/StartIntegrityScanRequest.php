<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Requests\Api;

use App\Http\Requests\Request;

final class StartIntegrityScanRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
