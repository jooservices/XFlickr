<?php

declare(strict_types=1);

namespace Database\Factories\Crawler;

use Database\Factories\Concerns\GeneratesFlickrNsid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Crawler\Models\Photo;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    use GeneratesFlickrNsid;

    protected $model = Photo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flickr_photo_id' => (string) fake()->unique()->numerify('#########'),
            'owner_nsid' => $this->flickrNsid(),
            'title' => fake()->sentence(3),
            'secret' => fake()->bothify('??????????'),
            'server' => (string) fake()->numberBetween(1, 9999),
            'farm' => fake()->numberBetween(1, 9),
            'raw_payload' => [],
        ];
    }
}
