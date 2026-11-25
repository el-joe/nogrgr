<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Exceptions\AiRequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Minimal HTTP client for any OpenAI-compatible API (OpenAI, FreeLLMAPI): chat
 * completions and the model list. Retries 429/5xx with backoff, then maps failures
 * to AiErrorType. Used by every AI translation provider.
 */
final class OpenAiCompatibleClient
{
    /**
     * Backoff between attempts on 429 / 5xx / connection errors (ms).
     *
     * @var list<int>
     */
    public const RETRY_BACKOFF_MS = [500, 1500];

    public function __construct(private readonly AiProviderConfig $config) {}

    public function config(): AiProviderConfig
    {
        return $this->config;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options  extra body fields (temperature, response_format…)
     * @return array<string, mixed> decoded response
     */
    public function chat(array $messages, array $options = []): array
    {
        $this->guardConfigured();

        return $this->send(fn (PendingRequest $request): Response => $request->post('/chat/completions', [
            'model' => $this->config->model,
            'messages' => $messages,
            ...$options,
        ]));
    }

    /**
     * Content of the first choice of a chat completion.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function complete(array $messages, array $options = []): string
    {
        $content = data_get($this->chat($messages, $options), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Empty completion.');
        }

        return $content;
    }

    /**
     * Model ids the key can use (connection test).
     *
     * @return list<string>
     */
    public function models(): array
    {
        $this->guardConfigured();

        $payload = $this->send(fn (PendingRequest $request): Response => $request->get('/models'));
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Model list missing.');
        }

        return array_values(array_filter(array_map(static fn (mixed $model): ?string => is_array($model) && is_string($model['id'] ?? null) ? $model['id'] : null, $data)));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $request = Http::baseUrl($this->config->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($this->config->timeout)
            ->retry(
                count(self::RETRY_BACKOFF_MS) + 1,
                static fn (int $attempt): int => self::RETRY_BACKOFF_MS[$attempt - 1] ?? 1500,
                static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 500, 502, 503, 504], true)
                        && $exception->response->json('error.code') !== 'insufficient_quota'),
                throw: false,
            );

        if ($this->config->apiKey !== null) {
            $request = $request->withToken($this->config->apiKey);
        }

        try {
            $response = $call($request);
        } catch (ConnectionException $exception) {
            throw new AiRequestException(AiErrorType::Unavailable, $exception->getMessage(), $exception);
        }

        if ($response->failed()) {
            $code = $response->json('error.code');

            throw new AiRequestException(
                AiErrorType::fromResponse($response->status(), is_string($code) ? $code : null),
                is_string($response->json('error.message')) ? $response->json('error.message') : 'HTTP '.$response->status(),
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Response is not JSON.');
        }

        return $json;
    }

    private function guardConfigured(): void
    {
        if ($this->config->baseUrl === '' || ($this->config->provider->requiresApiKey() && $this->config->apiKey === null)) {
            throw new AiRequestException(AiErrorType::NotConfigured);
        }
    }
}
