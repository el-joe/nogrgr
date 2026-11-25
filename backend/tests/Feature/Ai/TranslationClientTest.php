<?php

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
| Ai\Contracts\TranslationClient over one OpenAI-compatible HTTP client (decisions
| #43/#44): provider resolution per purpose, request shape, glossary, key order,
| retries and typed errors.
*/

beforeEach(function (): void {
    Sleep::fake();
    config([
        'ai.translation' => ['provider' => 'freellmapi', 'custom_provider' => 'freellmapi', 'paid_provider' => 'openai'],
        'ai.providers.openai' => ['base_url' => 'https://api.openai.test/v1', 'api_key' => 'sk-test-key', 'model' => 'gpt-4.1-mini'],
        'ai.providers.freellmapi' => ['base_url' => 'http://llm.local:3001/v1', 'api_key' => null, 'model' => 'auto'],
    ]);
});

function translationCompletion(array $translations): array
{
    return ['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['translations' => $translations], JSON_UNESCAPED_UNICODE)]]]];
}

it('resolves the custom provider by default and the paid provider for full-store runs', function (): void {
    $factory = app(TranslationClientFactory::class);

    expect(app(TranslationClient::class)->provider())->toBe(TranslationProvider::FreeLlmApi)
        ->and($factory->for(TranslationPurpose::Custom)->provider())->toBe(TranslationProvider::FreeLlmApi)
        ->and($factory->for(TranslationPurpose::Paid)->provider())->toBe(TranslationProvider::OpenAi);

    config(['ai.translation.custom_provider' => 'openai']);
    expect($factory->for(TranslationPurpose::Custom)->provider())->toBe(TranslationProvider::OpenAi);
});

it('translates texts keeping keys and sending the glossary', function (): void {
    Http::fake(['api.openai.test/v1/chat/completions' => Http::response(translationCompletion(['title' => 'حذاء جري', 'body' => 'مرحبا {{ name }}']))]);

    $result = app(TranslationClientFactory::class)->for(TranslationPurpose::Paid)
        ->translate(['title' => 'Running shoe', 'body' => 'Hello {{ name }}', 'empty' => ''], 'en', 'ar', ['Nogrgr' => '']);

    expect($result)->toBe(['title' => 'حذاء جري', 'body' => 'مرحبا {{ name }}', 'empty' => '']);

    Http::assertSent(function (HttpRequest $request): bool {
        $messages = $request['messages'];

        return $request->hasHeader('Authorization', 'Bearer sk-test-key')
            && $request['model'] === 'gpt-4.1-mini'
            && $request['response_format'] === ['type' => 'json_object']
            && str_contains($messages[0]['content'], '"Nogrgr" → keep unchanged')
            && json_decode($messages[1]['content'], true) === ['texts' => ['title' => 'Running shoe', 'body' => 'Hello {{ name }}']];
    });
});

it('calls a FreeLLMAPI gateway without an API key', function (): void {
    Http::fake(['llm.local:3001/v1/chat/completions' => Http::response(translationCompletion(['0' => 'Bonjour']))]);

    expect(app(TranslationClient::class)->translate(['Hello'], 'en', 'fr'))->toBe([0 => 'Bonjour']);

    Http::assertSent(fn (HttpRequest $request): bool => ! $request->hasHeader('Authorization') && $request['model'] === 'auto');
});

it('retries rate-limited calls and then succeeds', function (): void {
    Http::fake(['api.openai.test/*' => Http::sequence()
        ->push(['error' => ['message' => 'slow down']], 429)
        ->push(translationCompletion(['a' => 'ب']))]);

    expect(app(TranslationClientFactory::class)->provider(TranslationProvider::OpenAi)->translate(['a' => 'b'], 'en', 'ar'))->toBe(['a' => 'ب']);
    Http::assertSentCount(2);
});

it('maps provider failures to typed errors', function (int $status, array $body, AiErrorType $type): void {
    Http::fake(['api.openai.test/*' => Http::response($body, $status)]);

    try {
        app(TranslationClientFactory::class)->provider(TranslationProvider::OpenAi)->translate(['a' => 'b'], 'en', 'ar');
        $this->fail('Expected AiRequestException');
    } catch (AiRequestException $exception) {
        expect($exception->type)->toBe($type)
            ->and($exception->getCode())->toBe($type->httpStatus());
    }
})->with([
    'bad key' => [401, ['error' => ['message' => 'Incorrect API key']], AiErrorType::AuthFailed],
    'quota' => [429, ['error' => ['code' => 'insufficient_quota', 'message' => 'quota']], AiErrorType::QuotaExhausted],
    'down' => [503, ['error' => ['message' => 'down']], AiErrorType::Unavailable],
    'missing keys' => [200, translationCompletion(['other' => 'x']), AiErrorType::InvalidResponse],
]);

it('refuses to call OpenAI without an API key', function (): void {
    config(['ai.providers.openai.api_key' => null]);
    Http::fake();

    expect(fn () => app(TranslationClientFactory::class)->provider(TranslationProvider::OpenAi)->translate(['a'], 'en', 'ar'))
        ->toThrow(AiRequestException::class);
    Http::assertNothingSent();
});
