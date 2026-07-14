<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JOOservices\Flickr\Client\FakeFlickrTransport;
use JOOservices\Flickr\Exceptions\AuthenticationException;
use Modules\Crawler\Models\Connection;
use Modules\Flickr\Events\FlickrAccountConnected;
use Modules\Flickr\Events\FlickrAccountDisconnected;
use Modules\Flickr\Services\FlickrAppProfileService;
use Modules\Flickr\Services\FlickrOAuthService;
use Modules\Flickr\Services\FlickrTokenHealthService;
use Modules\Flickr\Tests\TestCase;
use Tests\Support\CreatesFlickrConnection;
use Tests\Support\FlickrNsid;

final class FlickrOAuthServiceTest extends TestCase
{
    use CreatesFlickrConnection;

    public function test_status_reports_disconnected_when_no_active_connection(): void
    {
        $status = app(FlickrOAuthService::class)->status();

        $this->assertFalse($status['connected']);
        $this->assertNull($status['account']);
    }

    public function test_status_reports_connected_account(): void
    {
        $connection = $this->createFlickrConnection([
            'is_active' => true,
        ]);

        $status = app(FlickrOAuthService::class)->status();

        $this->assertTrue($status['connected']);
        $this->assertIsArray($status['account']);
        $this->assertSame($connection->connection_key, $status['account']['nsid']);
        $this->assertTrue($status['account']['is_connected']);
    }

    public function test_list_accounts_includes_connection_flags(): void
    {
        $connection = $this->createFlickrConnection([
            'username' => 'listed-user',
        ]);

        $accounts = app(FlickrOAuthService::class)->listAccounts();

        $this->assertCount(1, $accounts);
        $this->assertSame($connection->connection_key, $accounts[0]['nsid']);
        $this->assertSame('listed-user', $accounts[0]['username']);
        $this->assertTrue($accounts[0]['is_connected']);
    }

    public function test_disconnect_clears_connection_and_dispatches_event(): void
    {
        Event::fake([FlickrAccountDisconnected::class]);

        $connection = $this->createFlickrConnection();

        app(FlickrOAuthService::class)->disconnect($connection->connection_key);

        $fresh = Connection::query()->whereKey($connection->id)->firstOrFail();
        $this->assertSame('', $fresh->token_payload);
        $this->assertNotNull($fresh->disconnected_at);
        $this->assertFalse($fresh->is_active);

        Event::assertDispatched(FlickrAccountDisconnected::class, function (FlickrAccountDisconnected $event) use ($connection): bool {
            return $event->connectionKey === $connection->connection_key;
        });
    }

    public function test_disconnect_ignores_empty_connection_key(): void
    {
        Event::fake([FlickrAccountDisconnected::class]);

        app(FlickrOAuthService::class)->disconnect('');

        Event::assertNotDispatched(FlickrAccountDisconnected::class);
    }

    public function test_activate_sets_active_connection(): void
    {
        $first = $this->createFlickrConnection(['is_active' => true]);
        $second = $this->createFlickrConnection(['is_active' => false]);

        app(FlickrOAuthService::class)->activate($second->connection_key);

        $this->assertFalse($first->fresh()?->is_active);
        $this->assertTrue($second->fresh()?->is_active);
    }

    public function test_activate_ignores_empty_connection_key(): void
    {
        $connection = $this->createFlickrConnection(['is_active' => true]);

        app(FlickrOAuthService::class)->activate('');

        $this->assertTrue($connection->fresh()?->is_active);
    }

    public function test_list_connections_returns_registry_rows(): void
    {
        $connection = $this->createFlickrConnection();

        $connections = app(FlickrOAuthService::class)->listConnections();

        $this->assertCount(1, $connections);
        $this->assertTrue($connections->first()?->is($connection));
    }

    public function test_active_connection_returns_active_row(): void
    {
        $this->createFlickrConnection(['is_active' => false]);
        $active = $this->createFlickrConnection(['is_active' => true]);

        $resolved = app(FlickrOAuthService::class)->activeConnection();

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($active));
    }

    public function test_status_marks_disconnected_account_as_not_connected(): void
    {
        $connection = $this->createFlickrConnection();
        app(FlickrOAuthService::class)->disconnect($connection->connection_key);

        $accounts = app(FlickrOAuthService::class)->listAccounts();

        $this->assertCount(1, $accounts);
        $this->assertFalse($accounts[0]['is_connected']);
    }

    public function test_begin_returns_authorization_payload(): void
    {
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=request-token&oauth_token_secret=request-secret&oauth_callback_confirmed=true');

        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );

        $payload = $service->begin('main');

        $this->assertSame('request-token', $payload['oauth_token']);
        $this->assertSame('request-secret', $payload['oauth_token_secret']);
        $this->assertSame('main', $payload['app_profile']);
        $this->assertStringContainsString('request-token', $payload['url']);
    }

    public function test_complete_registers_connection_and_dispatches_event(): void
    {
        Event::fake([FlickrAccountConnected::class]);
        $this->mockFlickrTokenHealth(valid: true);

        $nsid = FlickrNsid::fake();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');

        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );

        $connection = $service->complete(
            oauthToken: 'request-token',
            oauthVerifier: 'verifier',
            oauthTokenSecret: 'request-secret',
        );

        $this->assertSame($nsid, $connection->connection_key);
        $this->assertSame('oauth-user', $connection->username);
        Event::assertDispatched(FlickrAccountConnected::class, function (FlickrAccountConnected $event) use ($nsid): bool {
            return $event->connectionKey === $nsid;
        });
    }

    public function test_complete_disconnects_when_token_health_probe_fails(): void
    {
        $this->mockFlickrTokenHealth(valid: false, errorMessage: 'Invalid auth token');

        $nsid = FlickrNsid::fake();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');

        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );

        $this->expectException(AuthenticationException::class);

        try {
            $service->complete('request-token', 'verifier', 'request-secret');
        } finally {
            $fresh = Connection::query()->where('connection_key', $nsid)->first();
            $this->assertNotNull($fresh?->disconnected_at);
        }
    }

    public function test_complete_logs_success_without_raw_tokens(): void
    {
        Event::fake([FlickrAccountConnected::class]);
        $this->mockFlickrTokenHealth(valid: true);
        Log::spy();

        $nsid = FlickrNsid::fake();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');

        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );

        $service->complete(
            oauthToken: 'request-token-secret-value',
            oauthVerifier: 'verifier-secret-value',
            oauthTokenSecret: 'request-secret-value',
            context: ['phase' => 'callback', 'oauth_token' => 'should-be-stripped'],
        );

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                if ($message !== 'Flickr OAuth complete succeeded.') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, 'request-token-secret-value')
                    && ! str_contains($encoded, 'verifier-secret-value')
                    && ! str_contains($encoded, 'request-secret-value')
                    && ! str_contains($encoded, 'should-be-stripped')
                    && isset($context['oauth_token_fp'])
                    && isset($context['connection_key_fp'])
                    && strlen((string) $context['oauth_token_fp']) === 12
                    && ($context['phase'] ?? null) === 'callback';
            });
    }

    public function test_complete_logs_failure_without_raw_tokens(): void
    {
        $this->mockFlickrTokenHealth(valid: false, errorMessage: 'Invalid auth token');
        Log::spy();

        $nsid = FlickrNsid::fake();
        $transport = FakeFlickrTransport::new()
            ->push('oauth_token=access-token&oauth_token_secret=access-secret&user_nsid='.$nsid.'&username=oauth-user&fullname=OAuth+User');

        $service = new FlickrOAuthService(
            app(FlickrAppProfileService::class),
            app(FlickrTokenHealthService::class),
            $transport,
        );

        try {
            $service->complete('raw-oauth-token', 'raw-verifier', 'raw-secret', context: [
                'oauth_token_secret' => 'must-not-log',
            ]);
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            // expected
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                if ($message !== 'Flickr OAuth complete failed.') {
                    return false;
                }

                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, 'raw-oauth-token')
                    && ! str_contains($encoded, 'raw-verifier')
                    && ! str_contains($encoded, 'raw-secret')
                    && ! str_contains($encoded, 'must-not-log')
                    && isset($context['oauth_token_fp'])
                    && strlen((string) $context['oauth_token_fp']) === 12
                    && isset($context['error']);
            });
    }
}
