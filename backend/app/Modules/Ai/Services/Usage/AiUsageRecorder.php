<?php

namespace App\Modules\Ai\Services\Usage;

use App\Modules\Ai\Contracts\ReportsTokenUsage;
use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use Closure;
use Throwable;

/**
 * Writes the AI usage log (central `ai_usage_logs`): the tokens a client reported while
 * a callback ran, with provider, model, feature and store. Logging never breaks the
 * caller.
 */
class AiUsageRecorder
{
    public function __construct(private readonly AiUsageLogRepositoryContract $logs) {}

    /**
     * Run $call and log the tokens $client used during it (also when it throws).
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    public function measure(TranslationClient $client, AiUsageContext $context, Closure $call): mixed
    {
        $before = self::usage($client);

        try {
            return $call();
        } finally {
            $after = self::usage($client);
            $this->record($client, $context, $after['prompt'] - $before['prompt'], $after['completion'] - $before['completion']);
        }
    }

    public function record(TranslationClient $client, AiUsageContext $context, int $prompt, int $completion): void
    {
        if ($prompt <= 0 && $completion <= 0) {
            return;
        }

        try {
            $this->logs->query()->withoutGlobalScopes()->create([
                'tenant_id' => $context->tenantId ?? (tenancy()->initialized ? (int) tenant()?->getTenantKey() : null),
                'feature' => $context->feature,
                'provider' => $client->provider()->value,
                'model' => AiProviderConfig::fromConfig($client->provider())->model,
                'prompt_tokens' => max(0, $prompt),
                'completion_tokens' => max(0, $completion),
                'subject' => $context->subject,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array{prompt: int, completion: int}
     */
    public static function usage(TranslationClient $client): array
    {
        return $client instanceof ReportsTokenUsage ? $client->tokenUsage() : ['prompt' => 0, 'completion' => 0];
    }
}
