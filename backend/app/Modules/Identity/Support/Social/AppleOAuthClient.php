<?php

namespace App\Modules\Identity\Support\Social;

use App\Modules\Identity\Contracts\SocialOAuthClient;
use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Exceptions\SocialAuthException;
use App\Modules\Integrations\Exceptions\ConnectionTestFailed;
use App\Modules\Integrations\Support\AppleClientSecret;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sign in with Apple: authorization (response_mode=form_post, so the callback is a
 * POST) → token endpoint with the ES256 client secret → id_token claims. The id_token
 * comes straight from Apple's token endpoint over TLS; iss, aud, exp and the nonce are
 * still checked. The name is only posted (`user`) on the first authorization.
 */
class AppleOAuthClient implements SocialOAuthClient
{
    public const AUTHORIZE_URL = 'https://appleid.apple.com/auth/authorize';

    public const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public const ISSUER = 'https://appleid.apple.com';

    public const TIMEOUT_SECONDS = 10;

    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => (string) config('services.apple.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
            'nonce' => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function profile(string $code, string $nonce, string $redirectUri, array $callback = []): SocialProfileData
    {
        $clientId = (string) config('services.apple.client_id');

        try {
            $secret = AppleClientSecret::make(
                (string) config('services.apple.team_id'),
                $clientId,
                (string) config('services.apple.key_id'),
                (string) config('services.apple.private_key'),
            );

            $token = Http::asForm()->acceptJson()->timeout(self::TIMEOUT_SECONDS)->post(self::TOKEN_URL, [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]);
        } catch (ConnectionTestFailed $exception) {
            throw SocialAuthException::failed('Apple key: '.$exception->getMessage());
        } catch (ConnectionException $exception) {
            throw SocialAuthException::failed('Apple unreachable: '.$exception->getMessage());
        }

        $idToken = $token->json('id_token');

        if ($token->failed() || ! is_string($idToken)) {
            throw SocialAuthException::failed('Apple token exchange failed: '.($token->json('error') ?? $token->status()));
        }

        $claims = $this->claims($idToken);
        $audience = $claims['aud'] ?? null;

        if (($claims['iss'] ?? null) !== self::ISSUER
            || ! (is_array($audience) ? in_array($clientId, $audience, true) : $audience === $clientId)
            || ! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < time()
            || (isset($claims['nonce']) && ! hash_equals($nonce, (string) $claims['nonce']))
            || ! is_string($claims['sub'] ?? null) || ! is_string($claims['email'] ?? null)) {
            throw SocialAuthException::failed('Apple id_token rejected.');
        }

        return new SocialProfileData(
            provider: SocialProvider::Apple,
            id: $claims['sub'],
            email: mb_strtolower($claims['email']),
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            name: $this->name($callback['user'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        $segment = $parts[1] ?? '';
        $payload = count($parts) === 3 ? base64_decode(strtr($segment, '-_', '+/').str_repeat('=', (4 - strlen($segment) % 4) % 4), true) : false;
        $claims = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($claims) ? $claims : [];
    }

    private function name(mixed $user): ?string
    {
        $user = is_string($user) ? json_decode($user, true) : $user;
        $name = is_array($user) && is_array($user['name'] ?? null) ? $user['name'] : [];
        $full = trim(implode(' ', array_filter([$name['firstName'] ?? null, $name['lastName'] ?? null], 'is_string')));

        return $full === '' ? null : mb_substr($full, 0, 120);
    }
}
