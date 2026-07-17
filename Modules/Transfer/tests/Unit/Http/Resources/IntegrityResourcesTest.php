<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Unit\Http\Resources;

use Illuminate\Http\Request;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Http\Resources\IntegrityAnomalyResource;
use Modules\Transfer\Http\Resources\IntegrityScanResource;
use Modules\Transfer\Models\IntegrityAnomaly;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Tests\TestCase;

final class IntegrityResourcesTest extends TestCase
{
    public function test_scan_resource_formats_lifecycle_fields(): void
    {
        $scan = IntegrityScan::factory()->create(['status' => IntegrityScanStatus::Completed]);

        $data = IntegrityScanResource::make($scan)->resolve(app(Request::class));

        $this->assertSame($scan->uuid, $data['id']);
        $this->assertSame(IntegrityScanStatus::Completed->value, $data['status']);
        $this->assertArrayHasKey('finished_at', $data);
    }

    public function test_anomaly_resource_does_not_expose_local_path(): void
    {
        $anomaly = IntegrityAnomaly::factory()->create(['type' => IntegrityAnomalyType::Missing]);

        $data = IntegrityAnomalyResource::make($anomaly)->resolve(app(Request::class));

        $this->assertSame($anomaly->uuid, $data['id']);
        $this->assertSame(IntegrityAnomalyType::Missing->value, $data['type']);
        $this->assertArrayNotHasKey('local_path', $data);
    }
}
