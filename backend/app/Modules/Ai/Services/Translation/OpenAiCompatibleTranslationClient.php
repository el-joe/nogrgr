<?php

namespace App\Modules\Ai\Services\Translation;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Support\OpenAiCompatibleClient;

/**
 * TranslationClient over a chat-completions API. Texts go out as a JSON object
 * (keys preserved) and must come back as `{"translations": {key: text}}` with the
 * same keys; anything else is an InvalidResponse. Texts are sent in chunks so one
 * call stays well inside the model's context.
 */
final class OpenAiCompatibleTranslationClient implements TranslationClient
{
    public const CHUNK_SIZE = 50;

    public function __construct(private readonly OpenAiCompatibleClient $client) {}

    public function provider(): TranslationProvider
    {
        return $this->client->config()->provider;
    }

    public function translate(array $texts, string $from, string $to, array $glossary = []): array
    {
        if ($texts === []) {
            return [];
        }

        $result = [];

        foreach (array_chunk($texts, self::CHUNK_SIZE, true) as $chunk) {
            $pending = array_filter($chunk, static fn (string $text): bool => trim($text) !== '');
            $translated = $pending === [] ? [] : $this->translateChunk($pending, $from, $to, $glossary);

            foreach ($chunk as $key => $text) {
                $result[$key] = $translated[(string) $key] ?? $text;
            }
        }

        return $result;
    }

    /**
     * @param  array<array-key, string>  $texts
     * @param  array<string, string>  $glossary
     * @return array<string, string>
     */
    private function translateChunk(array $texts, string $from, string $to, array $glossary): array
    {
        $payload = [];

        foreach ($texts as $key => $text) {
            $payload[(string) $key] = $text;
        }

        $content = $this->client->complete([
            ['role' => 'system', 'content' => $this->instructions($from, $to, $glossary)],
            ['role' => 'user', 'content' => (string) json_encode(['texts' => $payload], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], [
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);

        $decoded = json_decode($this->stripFence($content), true);
        $translations = is_array($decoded) ? ($decoded['translations'] ?? null) : null;

        if (! is_array($translations)) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Missing "translations" object.');
        }

        $result = [];

        foreach (array_keys($payload) as $key) {
            $value = $translations[$key] ?? null;

            if (! is_string($value) || trim($value) === '') {
                throw new AiRequestException(AiErrorType::InvalidResponse, 'Missing translation for key '.$key.'.');
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $glossary
     */
    private function instructions(string $from, string $to, array $glossary): string
    {
        $lines = [
            "You are a professional e-commerce translator. Translate every value of the \"texts\" object from locale \"{$from}\" to locale \"{$to}\".",
            'Reply with JSON only: {"translations": {<same keys>: <translated text>}}. Keep every key.',
            'Keep placeholders exactly as written ({{ name }}, {name}, :name, %s), HTML tags and attributes, Markdown, URLs, e-mail addresses, numbers and SKUs.',
            'Use natural, concise wording for an online store; do not add explanations.',
        ];

        if ($glossary !== []) {
            $terms = [];

            foreach ($glossary as $term => $translation) {
                $terms[] = $translation === '' ? "\"{$term}\" → keep unchanged" : "\"{$term}\" → \"{$translation}\"";
            }

            $lines[] = 'Glossary (always use these): '.implode('; ', $terms).'.';
        }

        return implode("\n", $lines);
    }

    private function stripFence(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $content);
        }

        return $content;
    }
}
