<?php

namespace App\Modules\Ai\Services\Translation;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Usage\AiUsageRecorder;
use App\Modules\Ai\Support\PlaceholderGuard;

/**
 * Shared translate step of every AI translation feature (paid run, weekly delta, product
 * "Translate with AI", admin catalog / static keys): texts are grouped by source locale,
 * each translation must keep the source's placeholders / tags / URLs (one retry per text
 * alone, then the item is rejected), and the tokens used go to the AI usage log.
 */
class GuardedTranslator
{
    public function __construct(private readonly AiUsageRecorder $usage) {}

    /**
     * Item key => translated fields, or null when a text lost its placeholders twice.
     *
     * @param  array<array-key, TranslationItemData>  $items
     * @param  array<string, string>  $glossary
     * @return array<string, array<string, string>|null>
     *
     * @throws AiRequestException
     */
    public function translate(TranslationClient $client, array $items, string $locale, array $glossary, AiUsageContext $context): array
    {
        return $this->usage->measure($client, $context, fn (): array => $this->run($client, $items, $locale, $glossary));
    }

    /**
     * @param  array<array-key, TranslationItemData>  $items
     * @param  array<string, string>  $glossary
     * @return array<string, array<string, string>|null>
     */
    private function run(TranslationClient $client, array $items, string $locale, array $glossary): array
    {
        $results = [];
        $groups = [];

        foreach ($items as $key => $item) {
            foreach ($item->texts as $field => $text) {
                $groups[$item->from[$field]][] = [(string) $key, $field, $text];
            }
        }

        foreach ($groups as $from => $entries) {
            $texts = [];

            foreach ($entries as $index => [, , $text]) {
                $texts['t'.$index] = $text;
            }

            $translated = $client->translate($texts, (string) $from, $locale, $glossary);

            foreach ($entries as $index => [$key, $field, $text]) {
                if (array_key_exists($key, $results) && $results[$key] === null) {
                    continue;
                }

                $checked = $this->checked($client, $text, (string) ($translated['t'.$index] ?? ''), (string) $from, $locale, $glossary);
                $results[$key] = $checked === null ? null : [...($results[$key] ?? []), $field => $checked];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, string>  $glossary
     */
    private function checked(TranslationClient $client, string $source, string $translation, string $from, string $locale, array $glossary): ?string
    {
        if (trim($translation) !== '' && PlaceholderGuard::preserved($source, $translation)) {
            return $translation;
        }

        $retry = (string) ($client->translate(['text' => $source], $from, $locale, $glossary)['text'] ?? '');

        return trim($retry) !== '' && PlaceholderGuard::preserved($source, $retry) ? $retry : null;
    }
}
