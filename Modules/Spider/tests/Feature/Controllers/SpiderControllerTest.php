<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Feature\Controllers;

use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class SpiderControllerTest extends TestCase
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
        RuntimeConfig::set('spider.enabled', false, 'bool');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.spider.start', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('error');
    }

    public function test_start_returns_success_when_planner_accepts(): void
    {
        RuntimeConfig::set('spider.enabled', true, 'bool');
        RuntimeConfig::set('spider.max_depth', 1, 'int');
        RuntimeConfig::set('spider.max_new_contacts_per_run', 10, 'int');
        RuntimeConfig::set('spider.max_contacts_total', 100, 'int');
        RuntimeConfig::refresh();

        $connection = $this->createFlickrConnection();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.spider.start', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('success', 'Spider run started for this account.');
    }

    public function test_stop_returns_error_when_no_active_run_exists(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.spider.stop', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('error', 'No active spider run for this account.');
    }

    public function test_stop_returns_success_when_active_run_is_paused(): void
    {
        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 1,
        ]);

        $response = $this->from('/flickr/accounts/'.$connection->public_id)
            ->post(route('flickr.spider.stop', $connection->public_id));

        $response->assertRedirect('/flickr/accounts/'.$connection->public_id);
        $response->assertSessionHas('success', 'Spider run paused.');
    }
}
