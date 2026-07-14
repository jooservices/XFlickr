<?php

declare(strict_types=1);

namespace Modules\Catalog\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Catalog\Http\Resources\CatalogRowResource;
use Tests\TestCase;

final class CatalogRowResourceTest extends TestCase
{
    public function test_returns_array_resource_as_is(): void
    {
        $row = ['id' => '1', 'title' => 'Photo'];

        $this->assertSame($row, (new CatalogRowResource($row))->toArray(Request::create('/')));
    }

    public function test_returns_empty_array_for_non_arrayable_object(): void
    {
        $resource = new CatalogRowResource((object) ['x' => 1]);

        $this->assertSame([], $resource->toArray(Request::create('/')));
    }
}
