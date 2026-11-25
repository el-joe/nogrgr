<?php

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Exceptions\SocialAuthException;

/**
 * OAuth 2 / OpenID Connect authorization-code client of one social provider (no
 * Socialite: Google and Apple are two small, well-defined flows over the Http facade).
 */
interface SocialOAuthClient
{
    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string;

    /**
     * Exchange the code for the provider's verified identity.
     *
     * @param  array<string, mixed>  $callback  the callback parameters (Apple posts `user`)
     *
     * @throws SocialAuthException
     */
    public function profile(string $code, string $nonce, string $redirectUri, array $callback = []): SocialProfileData;
}
