<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPagination;
use App\Http\Requests\Request;
use Tests\TestCase;

final class NormalizesPaginationTest extends TestCase
{
    public function test_page_and_per_page_are_clamped(): void
    {
        $request = new class extends Request
        {
            use NormalizesPagination;
        };

        $request->initialize(['page' => 0, 'per_page' => 200]);

        $this->assertSame(1, $request->page());
        $this->assertSame(100, $request->perPage());
    }
}
