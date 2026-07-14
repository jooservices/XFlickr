<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use Mockery;
use Modules\Storage\Models\StorageAccount;
use Modules\Storage\Services\StorageFlysystemFactory;
use Modules\Storage\Services\StorageR2ConnectionVerifier;
use Tests\TestCase;

final class StorageR2ConnectionVerifierTest extends TestCase
{
    public function test_verify_delegates_to_flysystem_factory(): void
    {
        $credentials = StorageAccount::factory()->r2()->make()->credentials ?? [];

        $factory = Mockery::mock(StorageFlysystemFactory::class);
        $factory->shouldReceive('verifyR2Credentials')
            ->once()
            ->with($credentials);

        $this->instance(StorageFlysystemFactory::class, $factory);

        app(StorageR2ConnectionVerifier::class)->verify($credentials);
    }
}
