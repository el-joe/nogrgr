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

    /**
     * Tokens reported by the provider since this client was built.
     *
     * @var array{prompt: int, completion: int}
     */
    private array $usage = ['prompt' => 0, 'completion' => 0];

    public function __construct(private readonly AiProviderConfig $config) {}

    /**
     * @return array{prompt: int, completion: int}
     */
    public function tokenUsage(): array
    {
        return $this->usage;
    }

    public function config(): AiProviderConfig
    {
        return $this->config;
    }

    /**
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  array<string, mixed>  $options  extra body fields (temperature, response_format…)
     * @return array<string, mixed> decoded response
     */
    public function chat(array $messages, array $options = []): array
    {
        $this->guardConfigured();

        $response = $this->send(fn (PendingRequest $request): Response => $request->post('/chat/completions', [
            'model' => $this->config->model,
            'messages' => $messages,
            ...$options,
        ]));

        $this->usage['prompt'] += (int) data_get($response, 'usage.prompt_tokens', 0);
        $this->usage['completion'] += (int) data_get($response, 'usage.completion_tokens', 0);

        return $response;
    }

    /**
     * Content of the first choice of a chat completion.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
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
     * Embedding vector of one text (POST /embeddings; OpenAI-only tools, image search).
     *
     * @return list<float>
     */
    public function embedding(string $input, string $model, ?int $dimensions = null): array
    {
        $this->guardConfigured();

        $response = $this->send(fn (PendingRequest $request): Response => $request->post('/embeddings', array_filter(['model' => $model, 'input' => $input, 'dimensions' => $dimensions], static fn (mixed $value): bool => $value !== null)));
        $this->usage['prompt'] += (int) data_get($response, 'usage.prompt_tokens', 0);
        $vector = data_get($response, 'data.0.embedding');

        if (! is_array($vector) || $vector === []) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Embedding missing.');
        }

        return array_values(array_map(static fn (mixed $value): float => (float) $value, $vector));
    }

    /**
     * Output text of an OpenAI Responses API call (POST /responses), e.g. with the built-in
     * `web_search` tool (AI price finder).
     *
     * @param  array<string, mixed>  $options  tools, temperature…
     */
    public function respond(string $input, array $options = []): string
    {
        $this->guardConfigured();

        $response = $this->send(fn (PendingRequest $request): Response => $request->post('/responses', [
            'model' => $this->config->model,
            'input' => $input,
            ...$options,
        ]));
        $this->usage['prompt'] += (int) data_get($response, 'usage.input_tokens', 0);
        $this->usage['completion'] += (int) data_get($response, 'usage.output_tokens', 0);

        $text = is_string($response['output_text'] ?? null) ? $response['output_text'] : '';

        foreach ((array) ($response['output'] ?? []) as $item) {
            foreach (is_array($item) ? (array) ($item['content'] ?? []) : [] as $part) {
                if (is_array($part) && ($part['type'] ?? null) === 'output_text' && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        if (trim($text) === '') {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Empty response.');
        }

        return $text;
    }

    /**
     * Generated images as raw binary PNG strings (POST /images/generations; AI logo).
     *
     * @return list<string>
     */
    public function images(string $prompt, string $model, int $count = 1, string $size = '1024x1024'): array
    {
        $this->guardConfigured();

        $body = ['model' => $model, 'prompt' => $prompt, 'n' => $count, 'size' => $size];

        if (str_starts_with($model, 'dall-e')) {
            $body['response_format'] = 'b64_json';
        }

        $response = $this->send(fn (PendingRequest $request): Response => $request->post('/images/generations', $body));
        $this->usage['prompt'] += (int) data_get($response, 'usage.input_tokens', 0);
        $this->usage['completion'] += (int) data_get($response, 'usage.output_tokens', 0);

        $images = [];

        foreach ((array) data_get($response, 'data', []) as $item) {
            $binary = is_array($item) && is_string($item['b64_json'] ?? null) ? base64_decode($item['b64_json'], true) : false;

            if (is_string($binary) && $binary !== '') {
                $images[] = $binary;
            }
        }

        if ($images === []) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'No image returned.');
        }

        return $images;
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
