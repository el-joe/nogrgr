<?php

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Exceptions\AiRequestException;

/**
 * AI translation through one OpenAI-compatible provider (decision #43). Resolve it
 * with `TranslationClientFactory::for(TranslationPurpose)`; the container binding
 * gives the *custom* provider (decision #44).
 */
interface TranslationClient
{
    public function provider(): TranslationProvider;

    /**
     * Translate every text from one locale to another, keeping order and keys,
     * placeholders ({{ var }}, :name, %s), HTML tags, URLs and glossary terms.
     *
     * @param  array<array-key, string>  $texts
     * @param  array<string, string>  $glossary  source term => fixed translation ('' = keep as is)
     * @return array<array-key, string> same keys as $texts
     *
     * @throws AiRequestException
     */
    public function translate(array $texts, string $from, string $to, array $glossary = []): array;
}
