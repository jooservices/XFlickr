<?php

declare(strict_types=1);

namespace Modules\Flickr\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

final class FlickrOAuthCallbackRequest extends Request
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'oauth_token' => ['required', 'string'],
            'oauth_verifier' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sessionSecret = (string) $this->session()->get('flickr_oauth_token_secret', '');

            if ($sessionSecret === '') {
                $validator->errors()->add('oauth_token', 'Flickr OAuth callback was incomplete.');

                return;
            }

            $sessionToken = (string) $this->session()->get('flickr_oauth_token', '');
            $oauthToken = (string) $this->query('oauth_token', '');

            if ($sessionToken !== '' && $oauthToken !== $sessionToken) {
                $validator->errors()->add('oauth_token', 'Flickr OAuth token mismatch.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first() ?? 'Flickr OAuth callback was incomplete.';

        throw new HttpResponseException(
            redirect()->route('settings.index', ['tab' => 'flickr'])->with('error', $message),
        );
    }

    public function oauthToken(): string
    {
        return (string) $this->query('oauth_token', '');
    }

    public function oauthVerifier(): string
    {
        return (string) $this->query('oauth_verifier', '');
    }

    public function sessionSecret(): string
    {
        return (string) $this->session()->get('flickr_oauth_token_secret', '');
    }

    public function appProfile(): string
    {
        return (string) $this->session()->get('flickr_app_profile', 'main');
    }
}
