<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Events;

use Modules\Storage\Events\StorageRemoteItemsRemoved;
use Modules\Storage\Tests\TestCase;

final class StorageRemoteItemsRemovedTest extends TestCase
{
    public function test_it_carries_account_and_remote_ids(): void
    {
        $event = new StorageRemoteItemsRemoved(42, ['remote-a', 'remote-b']);

        $this->assertSame(42, $event->storageAccountId);
        $this->assertSame(['remote-a', 'remote-b'], $event->remoteIds);
    }
}
