<?php

namespace App\Modules\Integrations\Services\Testing;

use App\Modules\Integrations\DTOs\ConnectionTestResult;
use App\Modules\Integrations\Enums\ConnectionErrorType;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\ConnectionTestFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * "Test connection" of a central gateway config: one cheap authenticated read (or
 * token request) against the gateway's sandbox / live API, chosen by mode.
 */
class GatewayConnectionTester
{
    public const TIMEOUT_SECONDS = 10;

    /**
     * Sandbox / live API roots per gateway (same host when the key decides the mode).
     *
     * @var array<string, array{test: string, live: string}>
     */
    public const BASE_URLS = [
        'stripe' => ['test' => 'https://api.stripe.com', 'live' => 'https://api.stripe.com'],
        'paypal' => ['test' => 'https://api-m.sandbox.paypal.com', 'live' => 'https://api-m.paypal.com'],
        'paymob' => ['test' => 'https://accept.paymob.com', 'live' => 'https://accept.paymob.com'],
        'tap' => ['test' => 'https://api.tap.company', 'live' => 'https://api.tap.company'],
        'myfatoorah' => ['test' => 'https://apitest.myfatoorah.com', 'live' => 'https://api.myfatoorah.com'],
        'moyasar' => ['test' => 'https://api.moyasar.com', 'live' => 'https://api.moyasar.com'],
        'tabby' => ['test' => 'https://api.tabby.ai', 'live' => 'https://api.tabby.ai'],
        'tamara' => ['test' => 'https://api-sandbox.tamara.co', 'live' => 'https://api.tamara.co'],
    ];

    /**
     * @param  array<string, string>  $credentials
     */
    public function test(PaymentGatewayCode $code, GatewayMode $mode, array $credentials): ConnectionTestResult
    {
        if ($code->isOffline()) {
            return ConnectionTestResult::success();
        }

        try {
            foreach ($code->credentialFields() as $field) {
                if ($field->required && blank($credentials[$field->key] ?? null)) {
                    throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured);
                }
            }

            $this->ensure($this->call($code, $mode, $credentials, self::BASE_URLS[$code->value][$mode->value]));
        } catch (ConnectionTestFailed $exception) {
            return ConnectionTestResult::failure($exception->type, $exception->detail);
        } catch (ConnectionException $exception) {
            return ConnectionTestResult::failure(ConnectionErrorType::Unreachable, $exception->getMessage());
        }

        return ConnectionTestResult::success();
    }

    /**
     * @param  array<string, string>  $credentials
     */
    private function call(PaymentGatewayCode $code, GatewayMode $mode, array $credentials, string $base): Response
    {
        $http = $this->http()->baseUrl($base);

        return match ($code) {
            PaymentGatewayCode::Stripe => $this->stripe($http, $mode, $credentials),
            PaymentGatewayCode::Paypal => $http->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
                ->asForm()->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']),
            PaymentGatewayCode::Paymob => $http->post('/api/auth/tokens', ['api_key' => $credentials['api_key']]),
            PaymentGatewayCode::Tap => $http->withToken($credentials['secret_key'])->post('/v2/charges/list', ['limit' => 1]),
            PaymentGatewayCode::Myfatoorah => $http->withToken($credentials['api_key'])->post('/v2/InitiatePayment', ['InvoiceAmount' => 1, 'CurrencyIso' => 'KWD']),
            PaymentGatewayCode::Moyasar => $http->withBasicAuth($credentials['secret_key'], '')->get('/v1/payments', ['per' => 1]),
            PaymentGatewayCode::Tabby => $http->withToken($credentials['secret_key'])->get('/api/v1/payments', ['limit' => 1]),
            PaymentGatewayCode::Tamara => $http->withToken($credentials['api_token'])->get('/checkout/payment-types', ['country' => 'SA', 'currency' => 'SAR', 'order_value' => 100]),
            PaymentGatewayCode::Cod => throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured),
        };
    }

    /**
     * Stripe keys carry their mode (sk_test_ / sk_live_, rk_* restricted keys).
     *
     * @param  array<string, string>  $credentials
     */
    private function stripe(PendingRequest $http, GatewayMode $mode, array $credentials): Response
    {
        $secret = $credentials['secret_key'];

        if (preg_match('/^(sk|rk)_'.$mode->value.'_/', $secret) !== 1 || ! str_starts_with($credentials['publishable_key'], 'pk_'.$mode->value.'_')) {
            throw new ConnectionTestFailed(ConnectionErrorType::Misconfigured, __('integrations::admin.gateways.mode_key_mismatch', ['mode' => $mode->label()]));
        }

        return $http->withToken($secret)->get('/v1/balance');
    }

    private function ensure(Response $response): void
    {
        if ($response->successful()) {
            if ($response->json('IsSuccess') === false) {
                throw new ConnectionTestFailed(ConnectionErrorType::AuthFailed, is_string($response->json('Message')) ? $response->json('Message') : null);
            }

            return;
        }

        $message = $response->json('error.message') ?? $response->json('error_description') ?? $response->json('message') ?? $response->json('Message');

        throw new ConnectionTestFailed(ConnectionErrorType::fromStatus($response->status()), is_string($message) ? $message : 'HTTP '.$response->status());
    }

    private function http(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)->acceptJson();
    }
}
