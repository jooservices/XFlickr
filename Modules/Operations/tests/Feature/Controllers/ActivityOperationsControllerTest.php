<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class ActivityOperationsControllerTest extends TestCase
{
    use SafeRefreshDatabase;

    public function test_activity_page_renders(): void
    {
        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Crawl/Activity'));
    }
}
