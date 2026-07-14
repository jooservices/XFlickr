<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Spider\Enums\SpiderRunStatus;

/** @extends Factory<ContactFullPassRun> */
class ContactFullPassRunFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = ContactFullPassRun::class;

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
