<?php

namespace App\Modules\Ai\Services\Translation;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiProviderConfig;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Support\OpenAiCompatibleClient;

/**
 * Builds the TranslationClient of a purpose (custom → FreeLLMAPI by default, paid →
 * OpenAI by default) or of an explicit provider, from config('ai.*').
 */
class TranslationClientFactory
{
    public function for(TranslationPurpose $purpose): TranslationClient
    {
        return $this->provider($this->providerFor($purpose));
    }

    public function provider(TranslationProvider $provider): TranslationClient
    {
        return new OpenAiCompatibleTranslationClient($this->http($provider));
    }

    public function http(TranslationProvider $provider): OpenAiCompatibleClient
    {
        return new OpenAiCompatibleClient(AiProviderConfig::fromConfig($provider));
    }

    public function providerFor(TranslationPurpose $purpose): TranslationProvider
    {
        $fallback = TranslationProvider::tryFrom((string) config('ai.translation.provider'))
            ?? ($purpose === TranslationPurpose::Paid ? TranslationProvider::OpenAi : TranslationProvider::FreeLlmApi);

        return TranslationProvider::tryFrom((string) config($purpose->configKey())) ?? $fallback;
    }
}
