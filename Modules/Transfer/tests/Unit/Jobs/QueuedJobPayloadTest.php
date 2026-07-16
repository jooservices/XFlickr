<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Jobs;

use BackedEnum;
use Modules\Transfer\Enums\TransferType;
use Modules\Transfer\Jobs\DownloadFileJob;
use Modules\Transfer\Jobs\FanOutTransferJob;
use Modules\Transfer\Jobs\UploadFileJob;
use Modules\Transfer\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

final class QueuedJobPayloadTest extends TestCase
{
    #[DataProvider('jobs')]
    public function test_job_constructor_payload_contains_only_scalar_null_or_enum_values(object $job): void
    {
        $reflection = new ReflectionClass($job);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            if (! $parameter->isPromoted()) {
                continue;
            }

            $property = $reflection->getProperty($parameter->getName());
            $value = $property->getValue($job);

            $this->assertTrue(
                $value === null || is_scalar($value) || $value instanceof BackedEnum,
                $job::class.'::$'.$property->getName().' must remain queue-serializable domain state.',
            );
        }
    }

    /** @return array<string, array{object}> */
    public static function jobs(): array
    {
        return [
            'download' => [new DownloadFileJob('flickr_photo', 'photo-1', 'owner@N01', 'owner@N01', 1)],
            'fan-out' => [new FanOutTransferJob(TransferType::Upload, 'owner@N01', 'contact@N01', 10, true)],
            'upload' => [new UploadFileJob(20, 10, 1, 5)],
        ];
    }
}
