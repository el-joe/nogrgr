<?php

namespace App\Shared\Validation;

use App\Shared\Support\Locale;
use Illuminate\Validation\Validator;

/**
 * Names per-locale fields of translatable inputs in messages: `title.en` reads
 * "العنوان (الإنجليزية)" in Arabic and "title (English)" in English, composed from
 * `validation.attributes.title` and `validation.locales.en` through
 * `validation.localized_attribute`. An explicit name for the full key (custom
 * attributes or `validation.attributes`) still wins.
 */
class LocalizedAttributeValidator extends Validator
{
    /**
     * @param  string  $attribute
     * @return string
     */
    public function getDisplayableAttribute($attribute)
    {
        if (preg_match('/^(?<field>.+)\.(?<locale>[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?)$/', (string) $attribute, $match) !== 1
            || isset($this->customAttributes[$attribute])
            || $this->getAttributeFromTranslations($attribute) !== null) {
            return parent::getDisplayableAttribute($attribute);
        }

        $localeName = $this->localeName($match['locale']);

        if ($localeName === null) {
            return parent::getDisplayableAttribute($attribute);
        }

        return $this->translator->get('validation.localized_attribute', [
            'attribute' => parent::getDisplayableAttribute($match['field']),
            'locale' => $localeName,
        ]);
    }

    private function localeName(string $code): ?string
    {
        $key = 'validation.locales.'.$code;
        $line = $this->translator->get($key);

        if (is_string($line) && $line !== $key) {
            return $line;
        }

        $enabled = Locale::enabled();

        return isset($enabled[$code]) ? $enabled[$code]['name'] : null;
    }
}
