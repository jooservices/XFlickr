<?php

declare(strict_types=1);

namespace Modules\Operations\Tests\Feature\Controllers;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\Support\IgnoresAuthentication;
use Tests\TestCase;

final class ActivityOperationsControllerGuestTest extends TestCase
{
    use IgnoresAuthentication;
    use SafeRefreshDatabase;

    public function test_activity_page_requires_authentication(): void
    {
        $this->get('/activity')->assertRedirect('/login');
    }
}
