<?php

declare(strict_types=1);

namespace Modules\Transfer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Models\IntegrityAnomaly;
use Modules\Transfer\Models\IntegrityScan;

/** @extends Factory<IntegrityAnomaly> */
class IntegrityAnomalyFactory extends Factory
{
    protected $model = IntegrityAnomaly::class;

    public function definition(): array
    {
        return [
            'integrity_scan_id' => IntegrityScan::factory(),
            'uuid' => (string) Str::uuid(),
            'type' => IntegrityAnomalyType::Missing,
            'local_path' => 'flickr/'.fake()->uuid().'/photos/'.fake()->numerify('#########').'_abc.jpg',
            'stored_file_id' => null,
            'connection_key' => fake()->uuid(),
            'source_id' => fake()->numerify('#########'),
            'metadata' => [],
            'resolved_at' => null,
            'resolution' => null,
        ];
    }
}
