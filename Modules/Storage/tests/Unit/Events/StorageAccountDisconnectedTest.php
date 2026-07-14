<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Events;

use Modules\Storage\Enums\StorageDriver;
use Modules\Storage\Events\StorageAccountDisconnected;
use Tests\TestCase;

final class StorageAccountDisconnectedTest extends TestCase
{
    public function test_payload_and_aggregate_id_expose_account_metadata(): void
    {
        $accountId = fake()->numberBetween(1, 9999);
        $event = new StorageAccountDisconnected($accountId, StorageDriver::OneDrive->value);

        $this->assertSame([
            'account_id' => $accountId,
            'provider' => StorageDriver::OneDrive->value,
        ], $event->payload());
        $this->assertSame((string) $accountId, $event->aggregateId());
    }
}
