<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Dto;

use Modules\Flickr\Dto\ResolvedPhotoSource;
use Modules\Flickr\Tests\TestCase;

final class ResolvedPhotoSourceTest extends TestCase
{
    public function test_can_be_constructed_with_all_properties(): void
    {
        $url = fake()->url();
        $destinationPath = 'downloads/'.fake()->uuid().'.jpg';
        $originalName = fake()->lexify('photo-????.jpg');
        $variant = 'Original';
        $metadata = ['width' => 1024, 'height' => 768];

        $dto = new ResolvedPhotoSource($url, $destinationPath, $originalName, $variant, $metadata);

        $this->assertSame($url, $dto->url);
        $this->assertSame($destinationPath, $dto->destinationPath);
        $this->assertSame($originalName, $dto->originalName);
        $this->assertSame($variant, $dto->variant);
        $this->assertSame($metadata, $dto->metadata);
    }

    public function test_metadata_defaults_to_empty_array(): void
    {
        $url = fake()->url();
        $destinationPath = 'downloads/'.fake()->uuid().'.jpg';
        $originalName = fake()->lexify('photo-????.jpg');
        $variant = 'Original';

        $dto = new ResolvedPhotoSource($url, $destinationPath, $originalName, $variant);

        $this->assertSame([], $dto->metadata);
    }
}
