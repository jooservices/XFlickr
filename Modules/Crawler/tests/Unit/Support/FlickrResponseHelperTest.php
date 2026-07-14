<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\Support;

use Modules\Crawler\Support\FlickrResponseHelper;
use Modules\Crawler\Tests\TestCase;

final class FlickrResponseHelperTest extends TestCase
{
    public function test_list_items_returns_nested_list(): void
    {
        $items = FlickrResponseHelper::listItems([
            'contacts' => [
                'contact' => [
                    ['nsid' => '1@N01'],
                    ['nsid' => '2@N01'],
                ],
            ],
        ], 'contacts', 'contact');

        $this->assertCount(2, $items);
    }

    public function test_list_items_wraps_single_object(): void
    {
        $items = FlickrResponseHelper::listItems([
            'photosets' => ['photoset' => ['id' => 'ps-1']],
        ], 'photosets', 'photoset');

        $this->assertCount(1, $items);
        $this->assertSame('ps-1', $items[0]['id']);
    }

    public function test_list_items_returns_empty_when_path_missing(): void
    {
        $this->assertSame([], FlickrResponseHelper::listItems([], 'missing'));
        $this->assertSame([], FlickrResponseHelper::listItems(['photos' => 'string'], 'photos'));
    }
}
