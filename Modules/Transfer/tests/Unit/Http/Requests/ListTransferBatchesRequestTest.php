<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Requests;

use Modules\Transfer\Http\Requests\Api\ListTransferBatchesRequest;
use Modules\Transfer\Tests\TestCase;

final class ListTransferBatchesRequestTest extends TestCase
{
    public function test_status_returns_null_for_empty_string(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['status' => '']);

        $this->assertNull($request->status());
    }

    public function test_status_returns_value_when_present(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['status' => 'running']);

        $this->assertSame('running', $request->status());
    }

    public function test_status_returns_null_when_absent(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET');

        $this->assertNull($request->status());
    }

    public function test_type_returns_null_for_empty_string(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['type' => '']);

        $this->assertNull($request->type());
    }

    public function test_type_returns_value_when_present(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['type' => 'download']);

        $this->assertSame('download', $request->type());
    }

    public function test_limit_defaults_to_20(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET');

        $this->assertSame(20, $request->limit());
    }

    public function test_limit_clamps_to_max_50(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['limit' => '100']);

        $this->assertSame(50, $request->limit());
    }

    public function test_limit_clamps_to_min_1(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['limit' => '0']);

        $this->assertSame(1, $request->limit());
    }

    public function test_is_active_returns_false_by_default(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET');

        $this->assertFalse($request->isActive());
    }

    public function test_is_active_returns_true_when_set(): void
    {
        $request = ListTransferBatchesRequest::create('/test', 'GET', ['active' => '1']);

        $this->assertTrue($request->isActive());
    }
}
