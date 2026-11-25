<?php

namespace App\Modules\Ai\DTOs;

use App\Modules\Ai\Enums\TranslationProvider;
use App\Shared\DTOs\BaseDTO;

/**
 * Connection of one OpenAI-compatible provider, read from config('ai.providers.*')
 * (overlaid from Admin › Integrations › AI translation).
 */
final class AiProviderConfig extends BaseDTO
{
    public function __construct(
        public readonly TranslationProvider $provider,
        public readonly string $baseUrl,
        public readonly ?string $apiKey,
        public readonly string $model,
        public readonly int $timeout = 60,
    ) {}

    public static function fromConfig(TranslationProvider $provider): self
    {
        $config = (array) config('ai.providers.'.$provider->value, []);
        $key = $config['api_key'] ?? null;

        return new self(
            provider: $provider,
            baseUrl: rtrim((string) ($config['base_url'] ?? '') ?: $provider->defaultBaseUrl(), '/'),
            apiKey: is_string($key) && $key !== '' ? $key : null,
            model: (string) ($config['model'] ?? '') ?: $provider->defaultModel(),
            timeout: (int) ($config['timeout'] ?? config('ai.timeout', 60)),
        );
    }
}
