<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Jobs;

use Modules\Storage\Jobs\DownloadFileJob;
use Tests\TestCase;

final class DownloadFileJobTest extends TestCase
{
    public function test_job_dispatches_on_correct_queue(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $this->assertSame('xflickr-downloads', $job->queue);
    }

    public function test_retry_until_is_at_least_6_hours(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $retryUntil = $job->retryUntil();

        $this->assertGreaterThan(now()->addHours(5)->timestamp, $retryUntil->getTimestamp());
    }

    public function test_max_exceptions_is_three(): void
    {
        $job = new DownloadFileJob(
            sourceType: 'flickr_photo',
            sourceId: fake()->numerify('#########'),
            sourceOwner: fake()->numerify('########@N##'),
            connectionKey: fake()->uuid(),
        );

        $this->assertSame(3, $job->maxExceptions);
    }
}
