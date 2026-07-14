<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

trait GeneratesFlickrNsid
{
    protected function flickrNsid(): string
    {
        return fake()->unique()->numerify('##########@N##');
    }
}
