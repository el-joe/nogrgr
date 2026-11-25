<?php

namespace App\Modules\Identity\Support\Social;

use App\Modules\Identity\Contracts\SocialOAuthClient;
use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Exceptions\SocialAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Google OpenID Connect: authorization code → token endpoint → userinfo (sub, email,
 * email_verified, name), all server to server over TLS.
 */
class GoogleOAuthClient implements SocialOAuthClient
{
    public const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public const TIMEOUT_SECONDS = 10;

    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ]);
    }

    public function profile(string $code, string $nonce, string $redirectUri, array $callback = []): SocialProfileData
    {
        try {
            $token = Http::asForm()->acceptJson()->timeout(self::TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            $accessToken = $token->json('access_token');

            if ($token->failed() || ! is_string($accessToken)) {
                throw SocialAuthException::failed('Google token exchange failed: '.($token->json('error') ?? $token->status()));
            }

            $user = Http::withToken($accessToken)->acceptJson()->timeout(self::TIMEOUT_SECONDS)->get(self::USERINFO_URL);
        } catch (ConnectionException $exception) {
            throw SocialAuthException::failed('Google unreachable: '.$exception->getMessage());
        }

        $sub = $user->json('sub');
        $email = $user->json('email');

        if ($user->failed() || ! is_string($sub) || ! is_string($email) || $email === '') {
            throw SocialAuthException::failed('Google returned no e-mail.');
        }

        return new SocialProfileData(
            provider: SocialProvider::Google,
            id: $sub,
            email: mb_strtolower($email),
            emailVerified: filter_var($user->json('email_verified'), FILTER_VALIDATE_BOOL),
            name: is_string($user->json('name')) ? $user->json('name') : null,
        );
    }
}
