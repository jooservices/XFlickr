<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Feature\Jobs;

use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Storage\Jobs\UploadFileJob;
use Tests\TestCase;

final class UploadFileJobTest extends TestCase
{
    public function test_job_dispatches_on_correct_queue(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $this->assertSame('xflickr-uploads', $job->queue);
    }

    public function test_job_has_without_overlapping_middleware(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $middleware = $job->middleware();

        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_max_exceptions_is_three(): void
    {
        $job = new UploadFileJob(
            storedFileId: fake()->numberBetween(1, 100),
            storageAccountId: fake()->numberBetween(1, 10),
        );

        $this->assertSame(3, $job->maxExceptions);
    }
}
