<?php

declare(strict_types=1);

namespace Modules\Transfer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Models\IntegrityScan;

/** @extends Factory<IntegrityScan> */
class IntegrityScanFactory extends Factory
{
    protected $model = IntegrityScan::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'status' => IntegrityScanStatus::Pending,
            'disk' => 'local',
            'started_at' => null,
            'finished_at' => null,
            'orphaned_count' => 0,
            'missing_count' => 0,
            'error_message' => null,
        ];
    }
}
