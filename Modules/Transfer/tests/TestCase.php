<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests;

use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase as HostTestCase;

abstract class TestCase extends HostTestCase
{
    use SafeRefreshDatabase;
}
