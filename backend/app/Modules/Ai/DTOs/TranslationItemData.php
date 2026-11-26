<?php

namespace App\Modules\Ai\DTOs;

use App\Shared\DTOs\BaseDTO;

/**
 * One item of a store translation section: the source texts still to translate
 * (field => text, locked / already filled fields left out), each field's source locale
 * (English, else Arabic) and the hash of the item's full source (unchanged hash + done
 * = skipped on resume).
 */
final class TranslationItemData extends BaseDTO
{
    /**
     * @param  array<string, string>  $texts  field => text to translate
     * @param  array<string, string>  $from  field => source locale
     */
    public function __construct(
        public readonly string $key,
        public readonly array $texts,
        public readonly array $from,
        public readonly string $hash,
    ) {}

    /**
     * @param  array<string, array{0: string, 1: string}>  $sources  full source: field => [locale, text]
     * @param  list<string>  $translate  fields to translate now
     */
    public static function make(string $key, array $sources, array $translate): self
    {
        ksort($sources);
        $texts = [];
        $from = [];

        foreach ($translate as $field) {
            if (isset($sources[$field])) {
                [$from[$field], $texts[$field]] = $sources[$field];
            }
        }

        return new self($key, $texts, $from, sha1((string) json_encode($sources, JSON_UNESCAPED_UNICODE)));
    }
}
