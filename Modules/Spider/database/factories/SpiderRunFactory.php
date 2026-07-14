<?php

declare(strict_types=1);

namespace Modules\Spider\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;

/** @extends Factory<SpiderRun> */
class SpiderRunFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = SpiderRun::class;

    public function definition(): array
    {
        return [
            'connection_key' => $this->flickrNsid(),
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
            'contacts_discovered' => 0,
            'contacts_crawled' => 0,
            'paused_at' => null,
            'completed_at' => null,
        ];
    }
}
