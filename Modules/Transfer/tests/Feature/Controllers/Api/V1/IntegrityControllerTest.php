<?php

declare(strict_types=1);

namespace Modules\Transfer\Tests\Feature\Controllers\Api\V1;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Transfer\Enums\IntegrityAnomalyType;
use Modules\Transfer\Enums\IntegrityResolution;
use Modules\Transfer\Enums\IntegrityScanStatus;
use Modules\Transfer\Jobs\RunIntegrityScanJob;
use Modules\Transfer\Models\IntegrityAnomaly;
use Modules\Transfer\Models\IntegrityScan;
use Modules\Transfer\Tests\TestCase;

final class IntegrityControllerTest extends TestCase
{
    public function test_store_queues_a_scan(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/transfers/integrity-scans');

        $response
            ->assertAccepted()
            ->assertJsonPath('message', 'Integrity scan queued.')
            ->assertJsonPath('data.status', IntegrityScanStatus::Pending->value);
        Queue::assertPushed(RunIntegrityScanJob::class);
    }

    public function test_show_and_anomalies_return_opaque_identifiers(): void
    {
        $scan = IntegrityScan::factory()->create([
            'status' => IntegrityScanStatus::Completed,
            'orphaned_count' => 1,
        ]);
        $anomaly = IntegrityAnomaly::factory()->create([
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Orphaned,
            'local_path' => 'private/not-exposed.jpg',
        ]);

        $this->getJson("/api/v1/transfers/integrity-scans/{$scan->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $scan->uuid)
            ->assertJsonPath('data.orphaned_count', 1);

        $this->getJson("/api/v1/transfers/integrity-scans/{$scan->uuid}/anomalies")
            ->assertOk()
            ->assertJsonPath('data.0.id', $anomaly->uuid)
            ->assertJsonPath('data.0.type', IntegrityAnomalyType::Orphaned->value)
            ->assertJsonMissingPath('data.0.local_path');
    }

    public function test_resolution_request_validates_opaque_anomaly_ids(): void
    {
        $scan = IntegrityScan::factory()->create();

        $this->postJson("/api/v1/transfers/integrity-scans/{$scan->uuid}/resolutions", [
            'resolution' => IntegrityResolution::Delete->value,
            'anomaly_ids' => ['not-a-uuid'],
        ])->assertUnprocessable()->assertJsonValidationErrors('anomaly_ids.0');
    }

    public function test_delete_resolution_uses_the_server_recorded_path(): void
    {
        Storage::fake('local');
        $path = 'flickr/account/photos/orphan_abc.jpg';
        Storage::disk('local')->put($path, 'orphan');
        $scan = IntegrityScan::factory()->create(['disk' => 'local']);
        $anomaly = IntegrityAnomaly::factory()->create([
            'integrity_scan_id' => $scan->id,
            'type' => IntegrityAnomalyType::Orphaned,
            'local_path' => $path,
        ]);

        $this->postJson("/api/v1/transfers/integrity-scans/{$scan->uuid}/resolutions", [
            'resolution' => IntegrityResolution::Delete->value,
            'anomaly_ids' => [$anomaly->uuid],
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved_count', 1)
            ->assertJsonPath('data.skipped_count', 0);

        Storage::disk('local')->assertMissing($path);
        $this->assertNotNull($anomaly->fresh()->resolved_at);
    }
}
