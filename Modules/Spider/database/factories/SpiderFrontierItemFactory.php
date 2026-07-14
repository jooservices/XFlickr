<?php

declare(strict_types=1);

namespace Modules\Spider\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Models\SpiderFrontierItem;
use Modules\Spider\Models\SpiderRun;

/** @extends Factory<SpiderFrontierItem> */
class SpiderFrontierItemFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = SpiderFrontierItem::class;

    public function definition(): array
    {
        return [
            'spider_run_id' => SpiderRun::factory(),
            'contact_nsid' => $this->flickrNsid(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
            'crawled_at' => null,
        ];
    }
}
