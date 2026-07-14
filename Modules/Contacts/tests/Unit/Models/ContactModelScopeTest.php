<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Unit\Models;

use Modules\Contacts\Models\ContactAnnotation;
use Modules\Contacts\Models\ContactFullPassFrontierItem;
use Modules\Contacts\Models\ContactFullPassRun;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ContactModelScopeTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_full_pass_run_for_connection_and_running_scopes(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $running = ContactFullPassRun::factory()->create([
            'connection_key' => $matchKey,
            'status' => SpiderRunStatus::Running,
        ]);
        ContactFullPassRun::factory()->create([
            'connection_key' => $matchKey,
            'status' => SpiderRunStatus::Completed,
        ]);
        ContactFullPassRun::factory()->create([
            'connection_key' => $otherKey,
            'status' => SpiderRunStatus::Running,
        ]);

        $this->assertTrue(
            ContactFullPassRun::query()->forConnection($matchKey)->running()->whereKey($running->id)->exists(),
        );
        $this->assertFalse(
            ContactFullPassRun::query()->forConnection($otherKey)->running()->whereKey($running->id)->exists(),
        );
    }

    public function test_full_pass_frontier_status_scopes(): void
    {
        $pending = ContactFullPassFrontierItem::factory()->create([
            'status' => SpiderFrontierStatus::Pending,
        ]);
        ContactFullPassFrontierItem::factory()->create([
            'status' => SpiderFrontierStatus::Queued,
        ]);

        $this->assertTrue(ContactFullPassFrontierItem::query()->pending()->whereKey($pending->id)->exists());
        $this->assertFalse(ContactFullPassFrontierItem::query()->queued()->whereKey($pending->id)->exists());
        $this->assertSame(1, ContactFullPassFrontierItem::query()->withStatus(SpiderFrontierStatus::Queued)->count());
    }

    public function test_contact_annotation_for_connection_scope(): void
    {
        $matchKey = FlickrNsid::fake();
        $otherKey = FlickrNsid::fake();

        $match = ContactAnnotation::factory()->create(['connection_key' => $matchKey]);
        ContactAnnotation::factory()->create(['connection_key' => $otherKey]);

        $this->assertTrue(ContactAnnotation::query()->forConnection($matchKey)->whereKey($match->id)->exists());
        $this->assertFalse(ContactAnnotation::query()->forConnection($otherKey)->whereKey($match->id)->exists());
    }
}
