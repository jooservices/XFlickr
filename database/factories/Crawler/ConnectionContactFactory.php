<?php

declare(strict_types=1);

namespace Database\Factories\Crawler;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crawler\Models\ConnectionContact;

/**
 * @extends Factory<ConnectionContact>
 */
class ConnectionContactFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = ConnectionContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_key' => $this->flickrNsid(),
            'contact_nsid' => $this->flickrNsid(),
        ];
    }
}
