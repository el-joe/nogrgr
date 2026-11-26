<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\DTOs\TranslationItemData;
use Closure;

/**
 * Builds a TranslationItemData from per-field readers (shared by every source).
 */
final class SourceTexts
{
    public const LOCALES = ['en', 'ar'];

    /**
     * Each field is read in English, else Arabic.
     *
     * @param  list<string>  $fields
     * @param  Closure(string, string): string  $read  (field, locale) => text ('' = none)
     * @param  Closure(string): bool  $isLocked  field => hand-translated in the target locale
     */
    public static function item(string $key, array $fields, string $locale, bool $retranslate, Closure $read, Closure $isLocked): TranslationItemData
    {
        $sources = [];
        $translate = [];

        foreach ($fields as $field) {
            foreach (self::LOCALES as $code) {
                $text = $read($field, $code);

                if (trim($text) !== '') {
                    $sources[$field] = [$code, $text];

                    break;
                }
            }

            if (! isset($sources[$field]) || in_array($locale, self::LOCALES, true) || $isLocked($field)
                || (! $retranslate && trim($read($field, $locale)) !== '')) {
                continue;
            }

            $translate[] = $field;
        }

        return TranslationItemData::make($key, $sources, $translate);
    }
}
