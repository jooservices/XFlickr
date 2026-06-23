<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Flickr\BeginFlickrOAuthRequest;
use App\Http\Requests\Flickr\FlickrConnectionKeyRequest;
use App\Http\Requests\Flickr\FlickrOAuthCallbackRequest;
use App\Services\Flickr\FlickrOAuthService;
use Illuminate\Http\RedirectResponse;
use JOOservices\Flickr\Exceptions\AuthenticationException;
use JOOservices\Flickr\Exceptions\ConfigurationException;
use JOOservices\XFlickrCrawler\Exceptions\FlickrAppNotConfiguredException;

final class FlickrAuthController
{
    public function connect(BeginFlickrOAuthRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        try {
            $begin = $oauth->begin($request->appProfile());
        } catch (ConfigurationException|FlickrAppNotConfiguredException) {
            return redirect()->route('settings.index', ['tab' => 'flickr'])->with('error', 'Flickr app credentials are invalid or incomplete.');
        } catch (AuthenticationException $exception) {
            return redirect()->route('settings.index', ['tab' => 'flickr'])->with(
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
        $oauth->complete(
            $request->oauthToken(),
            $request->oauthVerifier(),
            $request->sessionSecret(),
            $request->appProfile(),
        );
        $request->session()->forget(['flickr_oauth_token', 'flickr_oauth_token_secret', 'flickr_app_profile']);

        return redirect()->route('flickr.accounts.index')->with('success', 'Flickr account connected.');
    }

    public function disconnect(FlickrConnectionKeyRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        $oauth->disconnect($request->connectionKey());

        return redirect()->route('settings.index', ['tab' => 'flickr'])->with('success', 'Flickr account disconnected.');
    }

    public function activate(FlickrConnectionKeyRequest $request, FlickrOAuthService $oauth): RedirectResponse
    {
        $oauth->activate($request->connectionKey());

        return redirect()->route('settings.index', ['tab' => 'flickr'])->with('success', 'Active Flickr account updated.');
    }
}
