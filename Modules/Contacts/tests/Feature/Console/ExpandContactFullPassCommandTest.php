<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Console;

use Illuminate\Support\Facades\Queue;
use Modules\Contacts\Database\Factories\ContactFullPassFrontierItemFactory;
use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Spider\Enums\SpiderFrontierStatus;
use Modules\Spider\Enums\SpiderRunStatus;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;
use Tests\TestCase;

final class ExpandContactFullPassCommandTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    public function test_stays_silent_when_nothing_queued(): void
    {
        $this->artisan('xflickr:contacts:full-pass-expand')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('Queued');
    }

    public function test_reports_queued_count_when_frontier_contacts_are_expanded(): void
    {
        Queue::fake();

        $connection = $this->createFlickrConnection();
        $run = ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);
        ContactFullPassFrontierItemFactory::new()->create([
            'contact_full_pass_run_id' => $run->id,
            'contact_nsid' => FlickrNsid::fake(),
            'depth' => 0,
            'status' => SpiderFrontierStatus::Pending,
        ]);

        $this->artisan('xflickr:contacts:full-pass-expand')
            ->expectsOutputToContain('Queued 1 full-pass frontier contact(s) for crawl.')
            ->assertSuccessful();
    }
}
