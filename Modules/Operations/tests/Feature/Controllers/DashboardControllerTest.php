<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_dashboard_renders_inertia_snapshot(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('snapshot'));
    }
}
