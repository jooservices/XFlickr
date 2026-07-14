<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\ContactFullPassFrontierItem;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Spider\Enums\SpiderFrontierStatus;

/** @extends Factory<ContactFullPassFrontierItem> */
class ContactFullPassFrontierItemFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = ContactFullPassFrontierItem::class;

    public function definition(): array
    {
        return [
            'contact_full_pass_run_id' => ContactFullPassRun::factory(),
            'contact_nsid' => $this->flickrNsid(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
            'crawled_at' => null,
        ];
    }
}
