<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Controllers;

use App\Support\ThirdPartyApiLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
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
            $begin = $oauth->begin($request->appProfile());
        } catch (ConfigurationException|FlickrAppNotConfiguredException $exception) {
            Log::warning('Flickr OAuth connect failed (configuration).', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('connections.index', ['provider' => 'flickr'])->with('error', 'Flickr app credentials are invalid or incomplete.');
        } catch (AuthenticationException $exception) {
            Log::warning('Flickr OAuth connect failed (authentication).', [
                'error' => $exception->getMessage(),
            ]);

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
            );
        } catch (AuthenticationException|ConfigurationException|FlickrAppNotConfiguredException $exception) {
            Log::warning('Flickr OAuth callback failed (configuration or authentication).', [
                'error' => $exception->getMessage(),
                'oauth_token_fp' => ThirdPartyApiLogger::fingerprint($request->oauthToken()),
            ]);

            return redirect()->route('connections.index', ['provider' => 'flickr'])->with(
                'error',
                'Flickr account could not be connected. Check app credentials and try again.',
            );
        } catch (Throwable $exception) {
            Log::warning('Flickr OAuth callback failed.', [
                'error' => $exception->getMessage(),
                'oauth_token_fp' => ThirdPartyApiLogger::fingerprint($request->oauthToken()),
            ]);

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
