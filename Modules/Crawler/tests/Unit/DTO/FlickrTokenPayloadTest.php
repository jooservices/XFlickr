<?php

declare(strict_types=1);

namespace Modules\Crawler\Tests\Unit\DTO;

use JOOservices\Dto\Exceptions\HydrationException;
use Modules\Crawler\DTO\FlickrTokenPayload;
use Modules\Crawler\Tests\TestCase;
use Tests\Support\FlickrNsid;

final class FlickrTokenPayloadTest extends TestCase
{
    public function test_hydrates_from_camel_case_keys(): void
    {
        $nsid = FlickrNsid::fake();

        $payload = FlickrTokenPayload::from([
            'oauthToken' => 'tok',
            'oauthTokenSecret' => 'sec',
            'userNsid' => $nsid,
            'username' => 'alice',
            'fullname' => 'Alice Example',
        ]);

        $this->assertSame('tok', $payload->oauthToken);
        $this->assertSame('sec', $payload->oauthTokenSecret);
        $this->assertSame($nsid, $payload->userNsid);
        $this->assertSame('alice', $payload->username);
        $this->assertSame('Alice Example', $payload->fullname);
    }

    public function test_transforms_snake_case_oauth_keys(): void
    {
        $nsid = FlickrNsid::fake();

        $payload = FlickrTokenPayload::from([
            'oauth_token' => 'snake-tok',
            'oauth_token_secret' => 'snake-sec',
            'user_nsid' => $nsid,
        ]);

        $this->assertSame('snake-tok', $payload->oauthToken);
        $this->assertSame('snake-sec', $payload->oauthTokenSecret);
        $this->assertSame($nsid, $payload->userNsid);
    }

    public function test_rejects_empty_token_secrets_as_security_guard(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('oauthToken and oauthTokenSecret');

        FlickrTokenPayload::from([
            'oauthToken' => '',
            'oauthTokenSecret' => '',
        ]);
    }
}
