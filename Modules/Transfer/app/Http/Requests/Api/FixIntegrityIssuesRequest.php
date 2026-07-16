<?php

declare(strict_types=1);

namespace Modules\Transfer\Http\Requests\Api;

use App\Http\Requests\Request;
use Modules\Transfer\Enums\IntegrityResolution;

final class FixIntegrityIssuesRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['resolution' => ['required', 'string', 'in:'.implode(',', array_map(static fn (IntegrityResolution $case): string => $case->value, IntegrityResolution::cases()))], 'anomaly_ids' => ['required', 'array', 'min:1', 'max:100'], 'anomaly_ids.*' => ['required', 'uuid', 'distinct']];
    }

    public function resolution(): IntegrityResolution
    {
        return IntegrityResolution::from((string) $this->validated('resolution'));
    }

    /** @return list<string> */
    public function anomalyIds(): array
    {
        return $this->validated('anomaly_ids');
    }
}
