<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Unit\Dto;

use Carbon\CarbonImmutable;
use Modules\Operations\Dto\ActivityFeedFilter;
use Tests\TestCase;

final class ActivityFeedFilterTest extends TestCase
{
    public function test_from_array_applies_defaults_and_caps(): void
    {
        $filter = ActivityFeedFilter::fromArray([
            'type' => 'domain',
            'correlation_id' => '42',
            'page' => 0,
            'per_page' => 999,
        ]);

        $this->assertSame('domain', $filter->type);
        $this->assertSame('42', $filter->correlationId);
        $this->assertSame(1, $filter->page);
        $this->assertSame(50, $filter->perPage);
        $this->assertInstanceOf(CarbonImmutable::class, $filter->from);
        $this->assertNull($filter->level);
    }

    public function test_without_level_clears_level_only(): void
    {
        $filter = ActivityFeedFilter::fromArray([
            'type' => 'audit',
            'level' => 'warning',
            'action_prefix' => 'crawler.',
            'correlation_id' => '7',
        ]);

        $cleared = $filter->withoutLevel();

        $this->assertSame('audit', $cleared->type);
        $this->assertNull($cleared->level);
        $this->assertSame('crawler.', $cleared->actionPrefix);
        $this->assertSame('7', $cleared->correlationId);
    }
}
