<?php

namespace App\Modules\Content\Http\Requests\Admin\Concerns;

use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Shared\Support\Locale;
use Illuminate\Support\Str;

/**
 * Shared helpers of the content admin forms: per-locale rules (the default locale
 * required, others optional), an allow-list HTML sanitiser for rich text, empty
 * translations dropped, and a slug generated from the English title when empty.
 *
 * @phpstan-require-extends \Illuminate\Foundation\Http\FormRequest
 */
trait TranslatedContent
{
    /**
     * @param  array<string, array{0: bool, 1: list<string>}>  $fields  field => [required in every locale, extra rules]
     * @return array<string, mixed>
     */
    protected function translatedRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field => [$required, $extra]) {
            $rules[$field] = [$required ? 'required' : 'nullable', 'array'];

            foreach (array_keys(Locale::enabled()) as $locale) {
                $rules[$field.'.'.$locale] = [$required && $locale === 'en' ? 'required' : 'nullable', 'string', ...$extra];
            }
        }

        return $rules;
    }

    /**
     * @param  list<string>  $htmlFields
     */
    protected function prepareTranslated(array $htmlFields, string $slugSource = 'title'): void
    {
        $merge = [];

        foreach ($htmlFields as $field) {
            $values = $this->input($field);

            if (is_array($values)) {
                $merge[$field] = array_map(fn ($html): ?string => is_string($html) && trim(strip_tags($html, '<img>')) !== '' ? EmailHtmlSanitizer::clean($html) : null, $values);
            }
        }

        $slug = $this->input('slug');
        $source = $this->input($slugSource);

        if ((! is_string($slug) || trim($slug) === '') && is_array($source)) {
            $merge['slug'] = Str::slug((string) ($source['en'] ?? reset($source) ?: ''));
        } elseif (is_string($slug)) {
            $merge['slug'] = Str::slug($slug);
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, string>
     */
    protected function translations(string $field): array
    {
        $values = $this->validated($field);

        return array_filter(is_array($values) ? $values : [], fn ($value): bool => is_string($value) && trim($value) !== '');
    }
}
