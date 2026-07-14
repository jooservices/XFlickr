<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use JOOservices\Flickr\Exceptions\AuthenticationException;
use JOOservices\Flickr\Exceptions\ConfigurationException;
use Modules\Crawler\Exceptions\FlickrAppNotConfiguredException;
use Modules\Flickr\Http\Requests\BeginFlickrOAuthRequest;
use Modules\Flickr\Http\Requests\FlickrConnectionKeyRequest;
use Modules\Flickr\Http\Requests\FlickrOAuthCallbackRequest;
use Modules\Flickr\Services\FlickrOAuthService;
use Throwable;

final class FlickrAuthController
{
    public function connect(BeginFlickrOAuthRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        try {
            $begin = $oauth->begin($request->appProfile(), [
                'phase' => 'connect',
            ]);
        } catch (ConfigurationException|FlickrAppNotConfiguredException) {
            return redirect()->route('connections.index', ['provider' => 'flickr'])->with('error', 'Flickr app credentials are invalid or incomplete.');
        } catch (AuthenticationException $exception) {
            return redirect()->route('connections.index', ['provider' => 'flickr'])->with(
                'error',
                'Flickr OAuth failed. Verify API key/secret and register callback URL '
                .route('flickr.callback', [], true)
                .' in your Flickr app. ('.$exception->getMessage().')',
            );
        }

        session([
            'flickr_oauth_token' => $begin['oauth_token'],
            'flickr_oauth_token_secret' => $begin['oauth_token_secret'],
            'flickr_app_profile' => $begin['app_profile'],
        ]);

        return redirect()->away($begin['url']);
    }

    public function callback(FlickrOAuthCallbackRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        try {
            $oauth->complete(
                $request->oauthToken(),
                $request->oauthVerifier(),
                $request->sessionSecret(),
                $request->appProfile(),
                ['phase' => 'callback'],
            );
        } catch (AuthenticationException|ConfigurationException|FlickrAppNotConfiguredException) {
            return redirect()->route('connections.index', ['provider' => 'flickr'])->with(
                'error',
                'Flickr account could not be connected. Check app credentials and try again.',
            );
        } catch (Throwable) {
            return redirect()->route('connections.index', ['provider' => 'flickr'])->with(
                'error',
                'Flickr account could not be connected due to an unexpected error.',
            );
        }

        $request->session()->forget(['flickr_oauth_token', 'flickr_oauth_token_secret', 'flickr_app_profile']);

        return redirect()->route('connections.index', ['provider' => 'flickr'])->with('success', 'Flickr account connected.');
    }

    public function disconnect(FlickrConnectionKeyRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        $oauth->disconnect($request->connectionKey());

        return redirect()->route('connections.index', ['provider' => 'flickr'])->with('success', 'Flickr account disconnected.');
    }

    public function activate(FlickrConnectionKeyRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        $oauth->activate($request->connectionKey());

        return redirect()->route('connections.index', ['provider' => 'flickr'])->with('success', 'Active Flickr account updated.');
    }
}
