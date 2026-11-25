<?php

namespace App\Modules\Ai\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Providers of AI translation (decision #43): OpenAI or a self-hosted
 * OpenAI-compatible FreeLLMAPI gateway. Every other AI tool is OpenAI only.
 */
enum TranslationProvider: string
{
    use HasEnumHelpers;

    case OpenAi = 'openai';
    case FreeLlmApi = 'freellmapi';

    public function defaultBaseUrl(): string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1',
            self::FreeLlmApi => 'http://localhost:3001/v1',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OpenAi => 'gpt-4.1-mini',
            self::FreeLlmApi => 'auto',
        };
    }

    /**
     * OpenAI always needs a key; a self-hosted gateway may run without one.
     */
    public function requiresApiKey(): bool
    {
        return $this === self::OpenAi;
    }
}
