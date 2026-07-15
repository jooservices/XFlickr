<?php

declare(strict_types=1);

namespace Modules\Settings\Tests\Unit\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use JOOservices\LaravelConfig\Models\Config as ConfigModel;
use Modules\Settings\Services\RuntimeConfigAdminService;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class RuntimeConfigAdminServiceTest extends TestCase
{
    use SafeRefreshDatabase;

    private RuntimeConfigAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available.');
        }

        Artisan::call('config-store:ensure-index');

        $this->service = app(RuntimeConfigAdminService::class);
    }

    protected function tearDown(): void
    {
        foreach (['xflickr.global_pause', 'spider.enabled'] as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }

        ConfigModel::query()->where('group', 'custom')->where('key', 'testvalue')->delete();
        RuntimeConfig::refresh();

        foreach (['custom.ratio', 'custom.payload', 'custom.empty'] as $path) {
            if (RuntimeConfig::has($path)) {
                RuntimeConfig::forget($path);
            }
        }
        ConfigModel::query()->where('group', 'custom')->whereIn('key', ['ratio', 'payload', 'empty'])->delete();
        RuntimeConfig::refresh();

        parent::tearDown();
    }

    public function test_index_payload_marks_defaults_and_stored_values(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $payload = $this->service->indexPayload();

        $globalPause = collect($payload['curated'])->firstWhere('path', 'xflickr.global_pause');

        $this->assertNotNull($globalPause);
        $this->assertTrue($globalPause['effective_value']);
        $this->assertTrue($globalPause['stored']);
        $this->assertSame('config-store', $globalPause['source']);
    }

    public function test_upsert_persists_allowed_path_and_refreshes_runtime(): void
    {
        $this->service->upsert([
            'path' => 'xflickr.global_pause',
            'type' => 'bool',
            'value' => 'true',
        ]);

        $this->assertTrue(RuntimeConfig::get('xflickr.global_pause'));
    }

    public function test_upsert_rejects_credential_paths(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->upsert([
            'path' => 'xflickr_app.main.api_key',
            'type' => 'string',
            'value' => 'secret',
        ]);
    }

    public function test_update_spider_mode_persists_all_fields_and_clears_pause_when_enabled(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->service->updateSpiderMode([
            'enabled' => true,
            'max_depth' => 2,
            'max_new_contacts_per_run' => 25,
            'max_contacts_total' => 500,
        ]);

        $this->assertTrue(RuntimeConfig::get('spider.enabled'));
        $this->assertSame(2, RuntimeConfig::get('spider.max_depth'));
        $this->assertSame(25, RuntimeConfig::get('spider.max_new_contacts_per_run'));
        $this->assertSame(500, RuntimeConfig::get('spider.max_contacts_total'));
        $this->assertFalse(RuntimeConfig::get('xflickr.global_pause'));
    }

    public function test_delete_rejects_core_paths(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->expectException(ValidationException::class);

        $this->service->delete('xflickr.global_pause');
    }

    public function test_reset_to_default_only_allows_core_paths(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->resetToDefault('custom.not.core');
    }

    public function test_custom_records_exclude_core_and_credential_prefixes(): void
    {
        ConfigModel::query()->create([
            'group' => 'custom',
            'key' => 'testvalue',
            'type' => 'string',
            'value' => 'hello',
        ]);
        RuntimeConfig::refresh();

        $payload = $this->service->indexPayload();
        $custom = collect($payload['custom']);

        $this->assertTrue($custom->contains(fn (array $row): bool => $row['path'] === 'custom.testvalue'));
        $this->assertFalse($custom->contains(fn (array $row): bool => str_starts_with($row['path'], 'xflickr_app.')));
    }

    public function test_delete_removes_custom_path_when_present(): void
    {
        ConfigModel::query()->create([
            'group' => 'custom',
            'key' => 'removable',
            'type' => 'string',
            'value' => 'bye',
        ]);
        RuntimeConfig::refresh();

        $this->service->delete('custom.removable');

        $this->assertFalse(RuntimeConfig::has('custom.removable'));
    }

    public function test_delete_is_noop_when_path_not_stored(): void
    {
        $this->service->delete('custom.missing');

        $this->assertFalse(RuntimeConfig::has('custom.missing'));
    }

    public function test_reset_to_default_clears_stored_core_value(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->service->resetToDefault('xflickr.global_pause');

        $this->assertFalse(RuntimeConfig::has('xflickr.global_pause'));
    }

    public function test_upsert_rejects_invalid_path_format(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->upsert([
            'path' => 'invalid-path',
            'type' => 'string',
            'value' => 'x',
        ]);
    }

    public function test_upsert_casts_numeric_types(): void
    {
        $this->service->upsert([
            'path' => 'spider.max_depth',
            'type' => 'int',
            'value' => '4',
        ]);

        $this->assertSame(4, RuntimeConfig::get('spider.max_depth'));
    }

    public function test_update_spider_mode_keeps_pause_when_disabled(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $this->service->updateSpiderMode([
            'enabled' => false,
            'max_depth' => 1,
            'max_new_contacts_per_run' => 10,
            'max_contacts_total' => 100,
        ]);

        $this->assertFalse(RuntimeConfig::get('spider.enabled'));
        $this->assertTrue(RuntimeConfig::get('xflickr.global_pause'));
    }

    public function test_upsert_casts_float_json_and_null_types(): void
    {
        ConfigModel::query()->create([
            'group' => 'custom',
            'key' => 'ratio',
            'type' => 'float',
            'value' => '0',
        ]);
        ConfigModel::query()->create([
            'group' => 'custom',
            'key' => 'payload',
            'type' => 'json',
            'value' => '[]',
        ]);
        ConfigModel::query()->create([
            'group' => 'custom',
            'key' => 'empty',
            'type' => 'null',
            'value' => 'x',
        ]);
        RuntimeConfig::refresh();

        $this->service->upsert([
            'path' => 'custom.ratio',
            'type' => 'float',
            'value' => '1.5',
        ]);
        $this->service->upsert([
            'path' => 'custom.payload',
            'type' => 'json',
            'value' => '{"enabled":true}',
        ]);
        $this->service->upsert([
            'path' => 'custom.empty',
            'type' => 'null',
            'value' => 'ignored',
        ]);

        $this->assertSame(1.5, RuntimeConfig::get('custom.ratio'));
        $this->assertSame(['enabled' => true], RuntimeConfig::get('custom.payload'));
        $this->assertNull(RuntimeConfig::get('custom.empty'));
    }

    public function test_upsert_horizon_path_terminates_workers(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('horizon:terminate')
            ->andReturn(0);

        $this->service->upsert([
            'path' => 'horizon.general_max_processes',
            'type' => 'int',
            'value' => '6',
        ]);

        $this->assertSame(6, RuntimeConfig::get('horizon.general_max_processes'));
    }
}
