<?php

namespace App\Modules\Integrations\Services\Testing;

use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Integrations\DTOs\ConnectionTestResult;
use App\Modules\Integrations\Enums\BroadcastDriver;
use App\Modules\Integrations\Enums\ConnectionErrorType;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Enums\MailDriver;
use App\Modules\Integrations\Enums\MailEncryption;
use App\Modules\Integrations\Exceptions\ConnectionTestFailed;
use App\Modules\Integrations\Mail\IntegrationTestMail;
use App\Modules\Integrations\Support\AppleClientSecret;
use App\Modules\Integrations\Support\PusherSignature;
use App\Modules\Integrations\Support\SocialRedirectUri;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * "Test connection" of every Integrations section, against the saved settings.
 * OAuth providers are probed with a deliberately invalid authorization code: a valid
 * client gets `invalid_grant`, wrong credentials get `invalid_client`.
 */
class IntegrationConnectionTester
{
    public const TIMEOUT_SECONDS = 10;

    public const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const APPLE_TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';

    public const PROBE_CODE = 'nogrgr-connection-test';

    /**
     * @param  array<string, mixed>  $settings  saved values of the section (decrypted)
     */
    public function test(IntegrationSection $section, array $settings, ?string $target = null): ConnectionTestResult
    {
        try {
            $detail = match ($section) {
                IntegrationSection::Mail => $this->mail($settings, (string) $target),
                IntegrationSection::Google => $this->google($settings),
                IntegrationSection::Apple => $this->apple($settings),
                IntegrationSection::Broadcasting => $this->broadcasting($settings),
                IntegrationSection::Cloudflare => $this->cloudflare($settings),
                IntegrationSection::Ai => $this->ai($settings, TranslationProvider::from((string) $target)),
            };
        } catch (ConnectionTestFailed $exception) {
            return ConnectionTestResult::failure($exception->type, $exception->detail);
        } catch (ConnectionException $exception) {
            return ConnectionTestResult::failure(ConnectionErrorType::Unreachable, $exception->getMessage());
        }

        return ConnectionTestResult::success($detail);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function mail(array $settings, string $recipient): ?string
    {
        $driver = MailDriver::tryFrom((string) ($settings['mailer'] ?? '')) ?? MailDriver::Smtp;

        if ($driver === MailDriver::Smtp && blank($settings['host'] ?? null)) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured);
        }

        config(['mail.mailers.integration_test' => $driver === MailDriver::Log
            ? ['transport' => 'log', 'channel' => config('mail.mailers.log.channel')]
            : [
                'transport' => 'smtp',
                'scheme' => (MailEncryption::tryFrom((string) ($settings['encryption'] ?? '')) ?? MailEncryption::Tls)->scheme(),
                'host' => (string) $settings['host'],
                'port' => (int) ($settings['port'] ?? 587),
                'username' => $settings['username'] ?? null,
                'password' => $settings['password'] ?? null,
                'timeout' => self::TIMEOUT_SECONDS,
            ]]);

        Mail::purge('integration_test');

        $mail = (new IntegrationTestMail((string) config('app.name')))
            ->from((string) ($settings['from_address'] ?? config('mail.from.address')), (string) ($settings['from_name'] ?? config('mail.from.name')));

        try {
            Mail::mailer('integration_test')->to($recipient)->send($mail);
        } catch (TransportExceptionInterface $exception) {
            $auth = str_contains($exception->getMessage(), '535') || stripos($exception->getMessage(), 'authenticat') !== false;

            throw new ConnectionTestFailed($auth ? ConnectionErrorType::AuthFailed : ConnectionErrorType::Unreachable, $exception->getMessage());
        } finally {
            Mail::purge('integration_test');
        }

        return $recipient;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function google(array $settings): ?string
    {
        $this->require($settings, ['client_id', 'client_secret']);

        $response = $this->http()->asForm()->post(self::GOOGLE_TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => self::PROBE_CODE,
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'redirect_uri' => SocialRedirectUri::for('google'),
        ]);

        return $this->oauthProbe($response);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function apple(array $settings): ?string
    {
        $this->require($settings, ['client_id', 'team_id', 'key_id', 'private_key']);

        $secret = AppleClientSecret::make((string) $settings['team_id'], (string) $settings['client_id'], (string) $settings['key_id'], (string) $settings['private_key']);

        $response = $this->http()->asForm()->post(self::APPLE_TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => self::PROBE_CODE,
            'client_id' => $settings['client_id'],
            'client_secret' => $secret,
            'redirect_uri' => SocialRedirectUri::for('apple'),
        ]);

        return $this->oauthProbe($response);
    }

    private function oauthProbe(Response $response): ?string
    {
        $error = $response->json('error');

        if ($error === 'invalid_grant') {
            return null;
        }

        if ($error === 'invalid_client' || in_array($response->status(), [401, 403], true)) {
            throw new ConnectionTestFailed(ConnectionErrorType::AuthFailed, is_string($response->json('error_description')) ? $response->json('error_description') : 'invalid_client');
        }

        throw new ConnectionTestFailed(ConnectionErrorType::fromStatus($response->status()), is_string($error) ? $error : 'HTTP '.$response->status());
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function broadcasting(array $settings): ?string
    {
        $this->require($settings, ['app_id', 'key', 'secret']);

        $driver = BroadcastDriver::tryFrom((string) ($settings['driver'] ?? '')) ?? BroadcastDriver::Reverb;

        if ($driver === BroadcastDriver::Pusher) {
            $base = 'https://api-'.((string) ($settings['cluster'] ?? '') ?: 'mt1').'.pusher.com';
        } else {
            $this->require($settings, ['host']);
            $base = ($settings['scheme'] ?? 'https').'://'.$settings['host'].':'.((int) ($settings['port'] ?? 443));
        }

        $path = '/apps/'.$settings['app_id'].'/channels';
        $query = PusherSignature::sign('GET', $path, (string) $settings['key'], (string) $settings['secret']);
        $response = $this->http()->get($base.$path, $query);

        if ($response->successful()) {
            return null;
        }

        throw new ConnectionTestFailed(ConnectionErrorType::fromStatus($response->status()), 'HTTP '.$response->status());
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function cloudflare(array $settings): ?string
    {
        $this->require($settings, ['api_token', 'zone_id']);

        $client = $this->http()->withToken((string) $settings['api_token'])->baseUrl(self::CLOUDFLARE_API);
        $verify = $client->get('/user/tokens/verify');

        if (! $verify->successful() || $verify->json('success') !== true || $verify->json('result.status') !== 'active') {
            throw new ConnectionTestFailed($verify->successful() ? ConnectionErrorType::AuthFailed : ConnectionErrorType::fromStatus($verify->status()), $this->cloudflareError($verify));
        }

        $zone = $client->get('/zones/'.rawurlencode((string) $settings['zone_id']));

        if (! $zone->successful() || $zone->json('success') !== true) {
            throw new ConnectionTestFailed($zone->status() >= 500 ? ConnectionErrorType::Unreachable : ConnectionErrorType::Misconfigured, $this->cloudflareError($zone));
        }

        return is_string($zone->json('result.name')) ? $zone->json('result.name') : null;
    }

    private function cloudflareError(Response $response): string
    {
        $message = $response->json('errors.0.message');

        return is_string($message) ? $message : 'HTTP '.$response->status();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function ai(array $settings, TranslationProvider $provider): ?string
    {
        $prefix = $provider->value.'_';
        $key = $settings[$prefix.'api_key'] ?? null;
        $model = (string) ($settings[$prefix.'model'] ?? '') ?: $provider->defaultModel();

        $client = new OpenAiCompatibleClient(new AiProviderConfig(
            provider: $provider,
            baseUrl: rtrim((string) ($settings[$prefix.'base_url'] ?? '') ?: $provider->defaultBaseUrl(), '/'),
            apiKey: is_string($key) && $key !== '' ? $key : null,
            model: $model,
            timeout: self::TIMEOUT_SECONDS,
        ));

        try {
            $models = $client->models();
        } catch (AiRequestException $exception) {
            throw new ConnectionTestFailed(match ($exception->type) {
                AiErrorType::NotConfigured => ConnectionErrorType::Misconfigured,
                AiErrorType::AuthFailed => ConnectionErrorType::AuthFailed,
                AiErrorType::QuotaExhausted => ConnectionErrorType::QuotaExhausted,
                AiErrorType::RateLimited => ConnectionErrorType::RateLimited,
                AiErrorType::Unavailable => ConnectionErrorType::Unreachable,
                AiErrorType::InvalidResponse => ConnectionErrorType::UnexpectedResponse,
            }, $exception->detail);
        }

        if ($model !== 'auto' && ! in_array($model, $models, true)) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, __('integrations::admin.test.model_missing', ['model' => $model]));
        }

        return trans_choice('integrations::admin.test.models_found', count($models), ['count' => count($models)]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $keys
     */
    private function require(array $settings, array $keys): void
    {
        foreach ($keys as $key) {
            if (blank($settings[$key] ?? null)) {
                throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured);
            }
        }
    }

    private function http(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)->acceptJson();
    }
}
