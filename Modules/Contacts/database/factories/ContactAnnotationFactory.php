<?php

declare(strict_types=1);

namespace Modules\Contacts\Database\Factories;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\ContactAnnotation;

/** @extends Factory<ContactAnnotation> */
class ContactAnnotationFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = ContactAnnotation::class;

    public function definition(): array
    {
        return [
            'connection_key' => $this->flickrNsid(),
            'contact_nsid' => $this->flickrNsid(),
            'note' => fake()->optional()->sentence(),
            'starred_at' => null,
        ];
    }

    public function starred(): static
    {
        return $this->state(fn () => ['starred_at' => now()]);
    }
}
