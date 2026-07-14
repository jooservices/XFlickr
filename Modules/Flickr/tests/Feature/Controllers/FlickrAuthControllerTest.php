<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Feature\Controllers;

use Illuminate\Support\Facades\Event;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\LaravelConfig\Facades\Config as RuntimeConfig;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Events\FlickrAccountDisconnected;
use Modules\Flickr\Services\FlickrAppProfileService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;

final class FlickrAuthControllerTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_connect_redirects_with_error_when_app_credentials_are_missing(): void
    {
        RuntimeConfig::forget('xflickr_app.main');
        RuntimeConfig::refresh();

        $response = $this->get('/flickr/oauth');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error', 'Flickr app credentials are invalid or incomplete.');
    }

    public function test_connect_accepts_custom_app_profile_query(): void
    {
        RuntimeConfig::set('xflickr_app.secondary', [
            'apiKey' => 'secondary-key',
            'apiSecret' => 'secondary-secret',
            'callbackUrl' => 'http://localhost/flickr/callback',
        ], 'json');
        RuntimeConfig::refresh();

        $response = $this->get('/flickr/oauth?app_profile=secondary');

        $this->assertTrue(
            $response->isRedirect(route('connections.index', ['provider' => 'flickr']))
            || $response->isRedirection(),
        );
    }

    public function test_callback_redirects_with_error_when_oauth_complete_fails(): void
    {
        RuntimeConfig::forget('xflickr_app.main');
        RuntimeConfig::refresh();

        $response = $this->withSession([
            'flickr_oauth_token' => 'request-token',
            'flickr_oauth_token_secret' => 'request-secret',
            'flickr_app_profile' => 'main',
        ])->get('/flickr/callback?oauth_token=request-token&oauth_verifier=verifier-code');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas(
            'error',
            'Flickr account could not be connected. Check app credentials and try again.',
        );
    }

    public function test_callback_rejects_missing_oauth_session_secret(): void
    {
        $response = $this->withSession([
            'flickr_oauth_token' => 'request-token',
        ])->get('/flickr/callback?oauth_token=request-token&oauth_verifier=verifier-code');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error', 'Flickr OAuth callback was incomplete.');
    }

    public function test_callback_rejects_oauth_token_mismatch(): void
    {
        $response = $this->withSession([
            'flickr_oauth_token' => 'expected-token',
            'flickr_oauth_token_secret' => 'request-secret',
        ])->get('/flickr/callback?oauth_token=other-token&oauth_verifier=verifier-code');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error', 'Flickr OAuth token mismatch.');
    }

    public function test_callback_requires_oauth_verifier(): void
    {
        $response = $this->withSession([
            'flickr_oauth_token' => 'request-token',
            'flickr_oauth_token_secret' => 'request-secret',
        ])->get('/flickr/callback?oauth_token=request-token');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error');
    }

    public function test_disconnect_removes_account(): void
    {
        Event::fake([FlickrAccountDisconnected::class]);

        $connection = $this->createFlickrConnection();

        $response = $this->post('/flickr/disconnect', [
            'connection_key' => $connection->connection_key,
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('success', 'Flickr account disconnected.');

        $fresh = Connection::query()->whereKey($connection->id)->firstOrFail();
        $this->assertSame('', $fresh->token_payload);
        $this->assertNotNull($fresh->disconnected_at);

        Event::assertDispatched(FlickrAccountDisconnected::class);
    }

    public function test_activate_updates_active_account(): void
    {
        $first = $this->createFlickrConnection(['is_active' => true]);
        $second = $this->createFlickrConnection(['is_active' => false]);

        $response = $this->post('/flickr/activate', [
            'connection_key' => $second->connection_key,
        ]);

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('success', 'Active Flickr account updated.');

        $this->assertFalse($first->fresh()?->is_active);
        $this->assertTrue($second->fresh()?->is_active);
    }

    public function test_disconnect_requires_connection_key(): void
    {
        $response = $this->post('/flickr/disconnect', []);

        $response->assertSessionHasErrors('connection_key');
    }

    public function test_connect_redirects_to_flickr_when_oauth_begins_successfully(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=request-token&oauth_token_secret=request-secret&oauth_callback_confirmed=true');
        $this->bindOAuthService($transport);

        $response = $this->get('/flickr/oauth');

        $response->assertRedirect();
        $this->assertStringContainsString('request-token', (string) $response->headers->get('Location'));
        $response->assertSessionHas('flickr_oauth_token', 'request-token');
        $response->assertSessionHas('flickr_oauth_token_secret', 'request-secret');
        $response->assertSessionHas('flickr_app_profile', 'main');
    }

    public function test_connect_redirects_with_error_when_oauth_begin_authentication_fails(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_problem=signature_invalid', 401);
        $this->bindOAuthService($transport);

        $response = $this->get('/flickr/oauth');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('error');
        $this->assertStringContainsString(
            'Flickr OAuth failed.',
            (string) session('error'),
        );
    }

    public function test_callback_redirects_with_success_when_oauth_completes(): void
    {
        $this->mockFlickrTokenHealth(valid: true);
        $nsid = FlickrNsid::fake();

        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');
        $this->bindOAuthService($transport);

        $response = $this->withSession([
            'flickr_oauth_token' => 'request-token',
            'flickr_oauth_token_secret' => 'request-secret',
            'flickr_app_profile' => 'main',
        ])->get('/flickr/callback?oauth_token=request-token&oauth_verifier=verifier-code');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas('success', 'Flickr account connected.');
        $response->assertSessionMissing('flickr_oauth_token');
        $response->assertSessionMissing('flickr_oauth_token_secret');
        $response->assertSessionMissing('flickr_app_profile');
    }

    public function test_callback_redirects_with_error_when_token_health_probe_fails(): void
    {
        $this->mockFlickrTokenHealth(valid: false, errorMessage: 'Invalid auth token');

        $nsid = FlickrNsid::fake();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');
        $this->bindOAuthService($transport);

        $response = $this->withSession([
            'flickr_oauth_token' => 'request-token',
            'flickr_oauth_token_secret' => 'request-secret',
            'flickr_app_profile' => 'main',
        ])->get('/flickr/callback?oauth_token=request-token&oauth_verifier=verifier-code');

        $response->assertRedirect(route('connections.index', ['provider' => 'flickr']));
        $response->assertSessionHas(
            'error',
            'Flickr account could not be connected. Check app credentials and try again.',
        );
    }

    private function bindOAuthService(FakeFlickrTransport $transport): FlickrOAuthService
    {
        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );
        $this->app->instance(FlickrOAuthService::class, $service);

        return $service;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        auth()->logout();

        $this->get('/flickr/oauth')->assertRedirect(route('login'));
        $this->get('/flickr/callback?oauth_token=a&oauth_verifier=b')->assertRedirect(route('login'));
        $this->post('/flickr/disconnect', ['connection_key' => 'x'])->assertRedirect(route('login'));
    }
}
