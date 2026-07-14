<?php

declare(strict_types=1);

namespace Modules\Flickr\Tests\Unit\Http\Requests;

use Modules\Flickr\Http\Requests\FlickrOAuthCallbackRequest;
use Modules\Flickr\Tests\TestCase;

final class FlickrOAuthCallbackRequestTest extends TestCase
{
    public function test_rules_require_oauth_token_and_verifier(): void
    {
        $request = FlickrOAuthCallbackRequest::create('/flickr/callback', 'GET');
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $validator = validator($request->query->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('oauth_token', $validator->errors()->toArray());
        $this->assertArrayHasKey('oauth_verifier', $validator->errors()->toArray());
    }

    public function test_after_validation_rejects_missing_session_secret(): void
    {
        $request = FlickrOAuthCallbackRequest::create(
            '/flickr/callback?oauth_token=tok&oauth_verifier=ver',
            'GET',
            ['oauth_token' => 'tok', 'oauth_verifier' => 'ver'],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setLaravelSession($this->app->make('session.store'));

        $validator = validator($request->query->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Flickr OAuth callback was incomplete.',
            $validator->errors()->first('oauth_token'),
        );
    }

    public function test_after_validation_rejects_token_mismatch(): void
    {
        $request = FlickrOAuthCallbackRequest::create(
            '/flickr/callback?oauth_token=other&oauth_verifier=ver',
            'GET',
            ['oauth_token' => 'other', 'oauth_verifier' => 'ver'],
        );
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $session = $this->app->make('session.store');
        $session->put('flickr_oauth_token', 'expected');
        $session->put('flickr_oauth_token_secret', 'secret');
        $request->setLaravelSession($session);

        $validator = validator($request->query->all(), $request->rules());
        $request->withValidator($validator);
        $validator->passes();

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Flickr OAuth token mismatch.',
            $validator->errors()->first('oauth_token'),
        );
    }

    public function test_accessors_read_oauth_query_and_session_values(): void
    {
        $request = FlickrOAuthCallbackRequest::create(
            '/flickr/callback?oauth_token=tok&oauth_verifier=ver',
            'GET',
            ['oauth_token' => 'tok', 'oauth_verifier' => 'ver'],
        );
        $request->setContainer($this->app);
        $session = $this->app->make('session.store');
        $session->put('flickr_oauth_token_secret', 'secret');
        $session->put('flickr_app_profile', 'secondary');
        $request->setLaravelSession($session);

        $this->assertSame('tok', $request->oauthToken());
        $this->assertSame('ver', $request->oauthVerifier());
        $this->assertSame('secret', $request->sessionSecret());
        $this->assertSame('secondary', $request->appProfile());
    }
}
