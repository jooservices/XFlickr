<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Exceptions;

use Modules\Storage\Exceptions\InvalidStoragePath;
use Modules\Storage\Tests\TestCase;

final class InvalidStoragePathTest extends TestCase
{
    public function test_for_includes_the_rejected_path(): void
    {
        $exception = InvalidStoragePath::for('../secret.jpg');

        $this->assertSame('Invalid storage path [../secret.jpg].', $exception->getMessage());
    }
}
