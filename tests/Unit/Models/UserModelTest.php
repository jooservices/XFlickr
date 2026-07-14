<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class UserModelTest extends TestCase
{
    use SafeRefreshDatabase;

    #[Test]
    public function scope_by_email_filters_matching_row(): void
    {
        $user = User::factory()->create(['email' => 'scoped@local']);
        User::factory()->create(['email' => 'other@local']);

        $found = User::query()->byEmail('scoped@local')->first();

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    #[Test]
    public function scope_active_and_inactive_partition_users(): void
    {
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->inactive()->create();

        $this->assertTrue(User::query()->active()->whereKey($active->id)->exists());
        $this->assertFalse(User::query()->active()->whereKey($inactive->id)->exists());
        $this->assertTrue(User::query()->inactive()->whereKey($inactive->id)->exists());
        $this->assertFalse(User::query()->inactive()->whereKey($active->id)->exists());
    }
}
