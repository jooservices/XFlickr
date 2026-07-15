<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Services;

use Illuminate\Support\Facades\Queue;
use Modules\Operations\Services\QueueDepthService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class QueueDepthServiceTest extends TestCase
{
    #[Test]
    public function depths_returns_sizes_for_known_queues(): void
    {
        Queue::shouldReceive('size')->with('xflickr')->once()->andReturn(2);
        Queue::shouldReceive('size')->with('xflickr-downloads')->once()->andReturn(0);
        Queue::shouldReceive('size')->with('xflickr-uploads')->once()->andReturn(4);

        $depths = app(QueueDepthService::class)->depths();

        $this->assertSame([
            'xflickr' => 2,
            'xflickr-downloads' => 0,
            'xflickr-uploads' => 4,
        ], $depths);
    }

    #[Test]
    public function depths_degrades_to_null_when_size_fails(): void
    {
        Queue::shouldReceive('size')->andThrow(new \RuntimeException('redis down'));

        $depths = app(QueueDepthService::class)->depths();

        $this->assertSame([
            'xflickr' => null,
            'xflickr-downloads' => null,
            'xflickr-uploads' => null,
        ], $depths);
    }
}
