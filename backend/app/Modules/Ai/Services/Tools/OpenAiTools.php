<?php

namespace App\Modules\Ai\Services\Tools;

use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\Usage\AiUsageRecorder;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use Closure;
use Illuminate\Support\Facades\Route;

/**
 * Runner of every OpenAI-only AI tool (decision #43: image search, price finder, social
 * posts, logo generator): checks the store's monthly plan quota, builds an OpenAI client
 * from the DB settings (Admin › Integrations › AI), runs the call and writes exactly one
 * AI usage log row for it (the row the quota counts).
 */
class OpenAiTools
{
    public function __construct(
        private readonly AiQuotaService $quota,
        private readonly AiUsageRecorder $recorder,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(OpenAiCompatibleClient, self): T  $call
     * @return T
     *
     * @throws AiQuotaExceededException|AiRequestException
     */
    public function run(AiUsageContext $context, Closure $call, ?int $timeout = null): mixed
    {
        if ($context->tenantId !== null) {
            $this->quota->ensureAvailable($context->feature, $context->tenantId);
        }

        $client = $this->client($timeout);
        $result = $call($client, $this);
        $usage = $client->tokenUsage();
        $this->recorder->recordCall($context, TranslationProvider::OpenAi->value, $client->config()->model, $usage['prompt'], $usage['completion']);

        return $result;
    }

    public function client(?int $timeout = null): OpenAiCompatibleClient
    {
        $config = AiProviderConfig::fromConfig(TranslationProvider::OpenAi);

        if ($timeout !== null) {
            $config = new AiProviderConfig($config->provider, $config->baseUrl, $config->apiKey, $config->model, $timeout);
        }

        return new OpenAiCompatibleClient($config);
    }

    /**
     * An OpenAI API key is saved (tools are offered / indexing is queued only then).
     */
    public static function configured(): bool
    {
        return AiProviderConfig::fromConfig(TranslationProvider::OpenAi)->apiKey !== null;
    }

    /**
     * The storefront offers "search by image" (camera button + modal).
     */
    public static function storefrontImageSearch(): bool
    {
        return Route::has('storefront.search.image') && self::configured();
    }

    public static function chatModel(): string
    {
        return AiProviderConfig::fromConfig(TranslationProvider::OpenAi)->model;
    }

    public static function embeddingModel(): string
    {
        $model = config('ai.providers.openai.embedding_model');

        return is_string($model) && $model !== '' ? $model : 'text-embedding-3-small';
    }

    public static function imageModel(): string
    {
        $model = config('ai.providers.openai.image_model');

        return is_string($model) && $model !== '' ? $model : 'gpt-image-1';
    }

    /**
     * Chat completion answered as a JSON object (response_format json_object).
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @return array<string, mixed>
     */
    public static function json(OpenAiCompatibleClient $client, array $messages, float $temperature = 0.4): array
    {
        $content = $client->complete($messages, ['response_format' => ['type' => 'json_object'], 'temperature' => $temperature]);
        $decoded = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $content) ?? $content), true);

        if (! is_array($decoded)) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Answer is not a JSON object.');
        }

        return $decoded;
    }
}
