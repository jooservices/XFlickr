<?php

declare(strict_types=1);

namespace Database\Factories\Crawler;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crawler\Models\Contact;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nsid' => $this->flickrNsid(),
            'username' => fake()->userName(),
            'realname' => fake()->name(),
            'friend' => fake()->boolean(),
            'family' => false,
            'raw_payload' => [],
        ];
    }
}
