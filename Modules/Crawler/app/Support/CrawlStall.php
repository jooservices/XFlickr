<?php

declare(strict_types=1);

namespace Modules\Crawler\Support;

use Carbon\CarbonImmutable;

final class CrawlStall
{
    public static function cutoff(): CarbonImmutable
    {
        $minutes = XFlickrConfig::crawlInt('stall_minutes', 15);

        return CarbonImmutable::now()->subMinutes($minutes);
    }
}
