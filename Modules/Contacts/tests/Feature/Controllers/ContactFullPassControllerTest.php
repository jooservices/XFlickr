<?php

declare(strict_types=1);

namespace Modules\Contacts\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Queue;
use Modules\Contacts\Database\Factories\ContactFullPassRunFactory;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class ContactFullPassControllerTest extends TestCase
{
    use CreatesFlickrConnection;
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }
    }

    public function test_start_returns_error_when_planner_refuses(): void
    {
        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.full-pass.start', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'spider run is already active',
            (string) session('error'),
        );
    }

    public function test_start_returns_success_when_planner_accepts(): void
    {
        Queue::fake();
        $connection = $this->createFlickrConnection();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.full-pass.start', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('success', 'Full contact pass started for this account.');
    }

    public function test_stop_returns_error_when_no_active_run_exists(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.full-pass.stop', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('error', 'No active full contact pass for this account.');
    }

    public function test_stop_returns_success_when_active_run_is_paused(): void
    {
        $connection = $this->createFlickrConnection();

        ContactFullPassRunFactory::new()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
        ]);

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.full-pass.stop', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('success', 'Full contact pass paused.');
    }
}
