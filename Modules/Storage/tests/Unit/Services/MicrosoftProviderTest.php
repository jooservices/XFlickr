<?php

declare(strict_types=1);

namespace Modules\Storage\Tests\Unit\Services;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Modules\Storage\Services\OAuth\MicrosoftProvider;
use ReflectionMethod;
use Tests\TestCase;

final class MicrosoftProviderTest extends TestCase
{
    public function test_map_user_to_object_prefers_mail_over_principal_name(): void
    {
        $provider = new MicrosoftProvider(
            Request::create('/'),
            'client-id',
            'client-secret',
            'http://localhost/callback',
        );

        $method = new ReflectionMethod(MicrosoftProvider::class, 'mapUserToObject');
        $method->setAccessible(true);

        $user = $method->invoke($provider, [
            'id' => fake()->uuid(),
            'displayName' => fake()->name(),
            'userPrincipalName' => fake()->userName().'@example.com',
            'mail' => fake()->safeEmail(),
        ]);

        $mapped = $user->getRaw();
        $this->assertSame($mapped['mail'], $user->getEmail());
    }

    public function test_map_user_to_object_falls_back_to_principal_name_when_mail_missing(): void
    {
        $provider = new MicrosoftProvider(
            Request::create('/'),
            'client-id',
            'client-secret',
            'http://localhost/callback',
        );

        $method = new ReflectionMethod(MicrosoftProvider::class, 'mapUserToObject');
        $method->setAccessible(true);

        $principal = fake()->userName().'@example.com';
        $user = $method->invoke($provider, [
            'id' => fake()->uuid(),
            'displayName' => fake()->name(),
            'userPrincipalName' => $principal,
        ]);

        $this->assertSame($principal, $user->getEmail());
    }

    public function test_auth_url_targets_microsoft_common_endpoint(): void
    {
        $provider = new MicrosoftProvider(
            Request::create('/'),
            'client-id',
            'client-secret',
            'http://localhost/callback',
        );

        $method = new ReflectionMethod(MicrosoftProvider::class, 'getAuthUrl');
        $method->setAccessible(true);

        $url = $method->invoke($provider, 'state-token');

        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $url);
        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString('state=state-token', $url);
    }

    public function test_token_url_targets_microsoft_common_endpoint(): void
    {
        $provider = new MicrosoftProvider(
            Request::create('/'),
            'client-id',
            'client-secret',
            'http://localhost/callback',
        );

        $method = new ReflectionMethod(MicrosoftProvider::class, 'getTokenUrl');
        $method->setAccessible(true);

        $this->assertSame(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            $method->invoke($provider),
        );
    }

    public function test_get_user_by_token_fetches_graph_profile(): void
    {
        $userId = fake()->uuid();
        $email = fake()->safeEmail();

        $provider = new MicrosoftProvider(
            Request::create('/'),
            'client-id',
            'client-secret',
            'http://localhost/callback',
        );

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => $userId,
                'displayName' => fake()->name(),
                'mail' => $email,
                'userPrincipalName' => fake()->userName().'@example.com',
            ], JSON_THROW_ON_ERROR)),
        ]));

        $httpProperty = new \ReflectionProperty(AbstractProvider::class, 'httpClient');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($provider, new GuzzleClient(['handler' => $handler]));

        $method = new ReflectionMethod(MicrosoftProvider::class, 'getUserByToken');
        $method->setAccessible(true);

        $user = $method->invoke($provider, 'graph-access-token');

        $this->assertSame($userId, $user['id']);
        $this->assertSame($email, $user['mail']);
    }
}
