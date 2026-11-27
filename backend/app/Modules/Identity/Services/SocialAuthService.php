<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\SocialOAuthClient;
use App\Modules\Identity\DTOs\SocialProfileData;
use App\Modules\Identity\Enums\SocialIntent;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Exceptions\SocialAuthException;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Support\Social\AppleOAuthClient;
use App\Modules\Identity\Support\Social\GoogleOAuthClient;
use App\Modules\Integrations\Support\SocialRedirectUri;
use App\Shared\Services\BaseService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Google / Apple sign-up and sign-in for store owners (WEB-016a):
 *
 *   begin()     random state + nonce kept in the session (10 minutes), provider URL
 *   handoff()   Apple posts the callback cross-site, where the lax session cookie is not
 *               sent: the POST parameters are parked in the cache under a random key and
 *               the browser is redirected to the GET callback (same site, session back)
 *   complete()  state check → code exchange → owner found / linked / created → owner
 *               guard signed in; returns the owner and the intent. Intent "link"
 *               (owner portal profile) only attaches the provider to the signed-in
 *               owner instead.
 */
class SocialAuthService extends BaseService
{
    private const SESSION_KEY = 'social_auth.pending';

    public const STATE_TTL_SECONDS = 600;

    public const HANDOFF_TTL_SECONDS = 120;

    public function __construct(
        private readonly Container $container,
        private readonly OwnerService $owners,
    ) {}

    public function client(SocialProvider $provider): SocialOAuthClient
    {
        /** @var SocialOAuthClient $client */
        $client = $this->container->make(match ($provider) {
            SocialProvider::Google => GoogleOAuthClient::class,
            SocialProvider::Apple => AppleOAuthClient::class,
        });

        return $client;
    }

    /**
     * @param  array<string, mixed>  $context  kept with the attempt (customer intent: the store hand-back)
     */
    public function begin(SocialProvider $provider, SocialIntent $intent, Request $request, array $context = []): string
    {
        $state = Str::random(40);
        $nonce = Str::random(40);

        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'nonce' => $nonce,
            'provider' => $provider->value,
            'intent' => $intent->value,
            'context' => $context,
            'expires_at' => now()->addSeconds(self::STATE_TTL_SECONDS)->getTimestamp(),
        ]);

        return $this->client($provider)->authorizationUrl($state, $nonce, SocialRedirectUri::for($provider->value));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function handoff(array $parameters): string
    {
        $key = Str::random(40);
        Cache::put('social-handoff:'.$key, array_intersect_key($parameters, array_flip(['code', 'state', 'error', 'user'])), self::HANDOFF_TTL_SECONDS);

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    public function pullHandoff(string $key): array
    {
        $parameters = Cache::pull('social-handoff:'.$key);

        return is_array($parameters) ? $parameters : [];
    }

    /**
     * The intent of the pending attempt (for the result page), without consuming it.
     */
    public function pendingIntent(Request $request): SocialIntent
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return SocialIntent::tryFrom(is_array($pending) ? (string) ($pending['intent'] ?? '') : '') ?? SocialIntent::Register;
    }

    /**
     * The context of the pending attempt, without consuming it.
     *
     * @return array<string, mixed>
     */
    public function pendingContext(Request $request): array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && is_array($pending['context'] ?? null) ? $pending['context'] : [];
    }

    /**
     * @param  array<string, mixed>  $parameters  callback query / handed-off POST
     * @return array{owner: Owner|null, intent: SocialIntent, profile?: SocialProfileData, context?: array<string, mixed>}
     *
     * @throws SocialAuthException
     */
    public function complete(SocialProvider $provider, array $parameters, Request $request): array
    {
        $pending = $request->session()->pull(self::SESSION_KEY);

        if (is_string($parameters['error'] ?? null) && $parameters['error'] !== '') {
            throw in_array($parameters['error'], ['access_denied', 'user_cancelled_authorize'], true)
                ? SocialAuthException::cancelled()
                : SocialAuthException::failed('Provider error: '.$parameters['error']);
        }

        $state = $parameters['state'] ?? null;
        $code = $parameters['code'] ?? null;

        if (! is_array($pending)
            || ($pending['provider'] ?? null) !== $provider->value
            || (int) ($pending['expires_at'] ?? 0) < now()->getTimestamp()
            || ! is_string($state) || ! hash_equals((string) ($pending['state'] ?? ''), $state)
            || ! is_string($code) || $code === '') {
            throw SocialAuthException::failed('Invalid or expired state.');
        }

        $profile = $this->client($provider)->profile($code, (string) $pending['nonce'], SocialRedirectUri::for($provider->value), $parameters);

        if (($pending['intent'] ?? null) === SocialIntent::Customer->value) {
            return ['owner' => null, 'intent' => SocialIntent::Customer, 'profile' => $profile, 'context' => is_array($pending['context'] ?? null) ? $pending['context'] : []];
        }

        if (($pending['intent'] ?? null) === SocialIntent::Link->value) {
            $owner = $this->owners->current();

            if ($owner === null) {
                throw SocialAuthException::failed('Link without an owner session.');
            }

            try {
                $this->owners->linkSocial($owner, $profile);
            } catch (ValidationException $exception) {
                throw SocialAuthException::failed($exception->getMessage(), $exception->getMessage());
            }

            return ['owner' => $owner, 'intent' => SocialIntent::Link];
        }

        try {
            $owner = $this->owners->fromSocial($profile);
        } catch (ValidationException $exception) {
            throw SocialAuthException::failed($exception->getMessage(), $exception->getMessage());
        }

        $this->owners->login($owner, $request);

        return ['owner' => $owner, 'intent' => SocialIntent::tryFrom((string) ($pending['intent'] ?? '')) ?? SocialIntent::Register];
    }
}
