<?php

declare(strict_types=1);

namespace Modules\Spider\Tests\Feature\Controllers\Api\V1;

use Modules\Spider\Enums\SpiderRunStatus;
use Modules\Spider\Models\SpiderRun;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\CreatesFlickrConnection;
use Tests\TestCase;

final class SpiderStatusControllerTest extends TestCase
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

    public function test_show_reports_inactive_when_no_run_exists(): void
    {
        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/spider-runs/current');

        $response->assertOk();
        $response->assertJsonPath('data.active', false);
        $response->assertJsonPath('data.run', null);
    }

    public function test_show_reports_active_running_run(): void
    {
        $connection = $this->createFlickrConnection();

        SpiderRun::query()->create([
            'connection_key' => $connection->connection_key,
            'status' => SpiderRunStatus::Running,
            'max_depth' => 2,
        ]);

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/spider-runs/current');

        $response->assertOk();
        $response->assertJsonPath('data.active', true);
        $response->assertJsonPath('data.run.status', SpiderRunStatus::Running->value);
        $response->assertJsonPath('data.run.max_depth', 2);
    }

    public function test_show_requires_authentication(): void
    {
        auth()->logout();

        $connection = $this->createFlickrConnection();

        $response = $this->getJson('/api/v1/flickr/accounts/'.$connection->public_id.'/spider-runs/current');

        $response->assertUnauthorized();
    }
}
