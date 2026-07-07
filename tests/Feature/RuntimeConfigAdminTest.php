<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Tests\Concerns\SafeRefreshDatabase;
use Tests\TestCase;

final class RuntimeConfigAdminTest extends TestCase
{
    use SafeRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available in this environment.');
        }

        Artisan::call('config-store:ensure-index');
    }

    public function test_can_store_core_runtime_config(): void
    {
        $response = $this->post('/settings/config', [
            'path' => 'xflickr.global_pause',
            'type' => 'bool',
            'value' => 'true',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'general']));
        $this->assertTrue(RuntimeConfig::get('xflickr.global_pause'));
    }

    public function test_cannot_delete_core_runtime_config(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $response = $this->delete('/settings/config/'.urlencode('xflickr.global_pause'));

        $response->assertSessionHasErrors('path');
        $this->assertTrue(RuntimeConfig::has('xflickr.global_pause'));
    }

    public function test_can_create_and_delete_custom_runtime_config(): void
    {
        $path = 'xflickr_custom.feature_flag';

        $create = $this->post('/settings/config', [
            'path' => $path,
            'type' => 'bool',
            'value' => 'true',
        ]);
        $create->assertRedirect();

        $this->assertTrue(RuntimeConfig::has($path));

        $delete = $this->delete('/settings/config/'.urlencode($path));
        $delete->assertRedirect();

        $this->assertFalse(RuntimeConfig::has($path));
    }

    public function test_can_reset_core_config_to_default(): void
    {
        RuntimeConfig::set('xflickr.global_pause', true, 'bool');
        RuntimeConfig::refresh();

        $response = $this->post('/settings/config/'.urlencode('xflickr.global_pause').'/reset');

        $response->assertRedirect(route('settings.index', ['tab' => 'general']));
        $this->assertFalse(RuntimeConfig::has('xflickr.global_pause'));
    }

    public function test_can_store_spider_runtime_config(): void
    {
        $response = $this->post('/settings/config', [
            'path' => 'spider.enabled',
            'type' => 'bool',
            'value' => 'true',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'general']));
        $this->assertTrue(RuntimeConfig::get('spider.enabled'));

        RuntimeConfig::forget('spider.enabled');
        RuntimeConfig::refresh();
    }

    public function test_settings_page_includes_runtime_config_payload(): void
    {
        $response = $this->get('/settings?tab=general');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Index')
            ->has('runtime_config.curated')
            ->has('runtime_config.custom'));
    }

    public function test_runtime_config_rejects_invalid_type(): void
    {
        if (! app()->bound('config-store')) {
            $this->markTestSkipped('Runtime config store is not available in this environment.');
        }

        $response = $this->post('/settings/config', [
            'path' => 'xflickr_custom.feature_flag',
            'type' => 'not-a-type',
            'value' => 'true',
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_storing_horizon_runtime_config_terminates_horizon(): void
    {
        Artisan::spy();

        $response = $this->post('/settings/config', [
            'path' => 'horizon.general_max_processes',
            'type' => 'int',
            'value' => '5',
        ]);

        $response->assertRedirect(route('settings.index', ['tab' => 'general']));
        $this->assertSame(5, RuntimeConfig::get('horizon.general_max_processes'));
        Artisan::shouldHaveReceived('call')->with('horizon:terminate');
    }

    public function test_resetting_horizon_runtime_config_terminates_horizon(): void
    {
        RuntimeConfig::set('horizon.general_max_processes', 5, 'int');
        RuntimeConfig::refresh();

        Artisan::spy();

        $response = $this->post('/settings/config/'.urlencode('horizon.general_max_processes').'/reset');

        $response->assertRedirect(route('settings.index', ['tab' => 'general']));
        $this->assertFalse(RuntimeConfig::has('horizon.general_max_processes'));
        Artisan::shouldHaveReceived('call')->with('horizon:terminate');
    }
}
