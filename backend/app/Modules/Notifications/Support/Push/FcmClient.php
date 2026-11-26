<?php

namespace App\Modules\Notifications\Support\Push;

use App\Modules\Integrations\Exceptions\ConnectionTestFailed;
use App\Modules\Integrations\Support\GoogleServiceAccountToken;
use App\Modules\Notifications\Enums\PushDeliveryResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging HTTP v1 sender. Project id and service account come from the
 * DB settings overlaid on `services.fcm.*` (Admin › Integrations › Firebase, never .env).
 * A 404 UNREGISTERED / 400 INVALID_ARGUMENT on the token means the install is gone.
 */
class FcmClient
{
    public const ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    public function isConfigured(): bool
    {
        return (bool) config('services.fcm.enabled')
            && filled(config('services.fcm.project_id'))
            && filled(config('services.fcm.service_account'));
    }

    /**
     * @param  array<string, string>  $data  string values only (FCM data payload)
     */
    public function send(string $token, string $title, string $body, array $data = []): PushDeliveryResult
    {
        if (! $this->isConfigured()) {
            return PushDeliveryResult::NotConfigured;
        }

        try {
            $accessToken = GoogleServiceAccountToken::accessToken((string) config('services.fcm.service_account'));
            $response = Http::timeout(10)->acceptJson()->withToken($accessToken)->post(sprintf(self::ENDPOINT, (string) config('services.fcm.project_id')), [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                    'android' => ['priority' => 'high'],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);
        } catch (ConnectionTestFailed|ConnectionException $exception) {
            Log::warning('FCM send failed', ['error' => $exception->getMessage()]);

            return PushDeliveryResult::Failed;
        }

        if ($response->successful()) {
            return PushDeliveryResult::Sent;
        }

        $status = (string) $response->json('error.status');
        $code = collect((array) $response->json('error.details'))->pluck('errorCode')->filter()->first();

        if ($response->status() === 404 || $status === 'NOT_FOUND' || $code === 'UNREGISTERED' || ($response->status() === 400 && $code === 'INVALID_ARGUMENT')) {
            return PushDeliveryResult::InvalidToken;
        }

        Log::warning('FCM send rejected', ['status' => $response->status(), 'error' => $status]);

        return PushDeliveryResult::Failed;
    }
}
