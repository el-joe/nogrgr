<?php

namespace App\Modules\Storefront\Support\CustomThemes;

use App\Modules\Storefront\Enums\CustomThemeSettingType;
use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Enums\StoreFont;

/**
 * theme.json schema (docs/themes/README.md § theme.json): validation of the manifest and
 * of the vendor's settings values against its `settings` schema (VEN-036b).
 *
 *   {"name": "Starter", "version": "1.0.0", "engine": "nogrgr-twig/1",
 *    "author": {"name": "…", "email": "…", "url": "https://…"}, "description": "…",
 *    "supports": ["home", "listing", …], "rtl": true,
 *    "settings": [{"id": "accent", "type": "color", "label": {"en": "Accent", "ar": "…"}, "default": "#FD6F04"}]}
 */
final class ThemeManifest
{
    public const ENGINE = 'nogrgr-twig/1';

    private const SEMVER = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[0-9A-Za-z.-]{1,20})?$/';

    private const SETTING_ID = '/^[a-z][a-z0-9_]{0,39}$/';

    /**
     * @param  mixed  $json  decoded theme.json
     * @return array{errors: list<string>, manifest: array<string, mixed>|null}
     */
    public static function validate(mixed $json): array
    {
        $errors = [];

        if (! is_array($json) || array_is_list($json)) {
            return ['errors' => ['theme.json must be a JSON object.'], 'manifest' => null];
        }

        $name = $json['name'] ?? null;
        if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 60) {
            $errors[] = 'theme.json: "name" is required (1–60 characters).';
        }

        $version = $json['version'] ?? null;
        if (! is_string($version) || preg_match(self::SEMVER, $version) !== 1) {
            $errors[] = 'theme.json: "version" must be a semantic version such as "1.0.0".';
        }

        if (($json['engine'] ?? null) !== self::ENGINE) {
            $errors[] = sprintf('theme.json: "engine" must be "%s".', self::ENGINE);
        }

        $author = $json['author'] ?? null;
        if (! is_array($author) || ! is_string($author['name'] ?? null) || trim($author['name']) === '' || mb_strlen($author['name']) > 80) {
            $errors[] = 'theme.json: "author.name" is required (1–80 characters).';
        } else {
            if (isset($author['email']) && (! is_string($author['email']) || filter_var($author['email'], FILTER_VALIDATE_EMAIL) === false)) {
                $errors[] = 'theme.json: "author.email" is not a valid e-mail address.';
            }
            if (isset($author['url']) && (! is_string($author['url']) || ! str_starts_with($author['url'], 'https://') || filter_var($author['url'], FILTER_VALIDATE_URL) === false)) {
                $errors[] = 'theme.json: "author.url" must be an https:// URL.';
            }
        }

        if (isset($json['description']) && (! is_string($json['description']) || mb_strlen($json['description']) > 500)) {
            $errors[] = 'theme.json: "description" must be text of at most 500 characters.';
        }

        $supports = $json['supports'] ?? null;
        $pages = array_map(static fn (CustomThemeTemplate $template): string => $template->value, CustomThemeTemplate::pages());
        if (! is_array($supports) || ! array_is_list($supports)) {
            $errors[] = 'theme.json: "supports" must list the pages the theme renders.';
        } else {
            $missing = array_diff($pages, $supports);
            $unknown = array_diff(array_filter($supports, 'is_string'), $pages);
            if ($missing !== []) {
                $errors[] = 'theme.json: "supports" is missing: '.implode(', ', $missing).'.';
            }
            if ($unknown !== []) {
                $errors[] = 'theme.json: "supports" has unknown pages: '.implode(', ', $unknown).'.';
            }
        }

        if (isset($json['rtl']) && ! is_bool($json['rtl'])) {
            $errors[] = 'theme.json: "rtl" must be true or false.';
        }

        $settings = $json['settings'] ?? [];
        if (! is_array($settings) || ! array_is_list($settings)) {
            $errors[] = 'theme.json: "settings" must be a list.';
            $settings = [];
        } elseif (count($settings) > CustomThemeLimits::MAX_SETTINGS) {
            $errors[] = sprintf('theme.json: at most %d settings.', CustomThemeLimits::MAX_SETTINGS);
        }

        $ids = [];
        $clean = [];
        foreach ($settings as $index => $setting) {
            $result = self::setting($setting, $index);
            array_push($errors, ...$result['errors']);

            if ($result['setting'] !== null) {
                if (isset($ids[$result['setting']['id']])) {
                    $errors[] = sprintf('theme.json: setting id "%s" is used twice.', $result['setting']['id']);
                }
                $ids[$result['setting']['id']] = true;
                $clean[] = $result['setting'];
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'manifest' => null];
        }

        return ['errors' => [], 'manifest' => [
            'name' => trim((string) $name),
            'version' => (string) $version,
            'engine' => self::ENGINE,
            'author' => array_filter(['name' => trim((string) $author['name']), 'email' => $author['email'] ?? null, 'url' => $author['url'] ?? null]),
            'description' => isset($json['description']) ? (string) $json['description'] : null,
            'supports' => array_values(array_intersect($pages, (array) $supports)),
            'rtl' => (bool) ($json['rtl'] ?? true),
            'settings' => $clean,
        ]];
    }

    /**
     * @return array{errors: list<string>, setting: array<string, mixed>|null}
     */
    private static function setting(mixed $setting, int $index): array
    {
        $at = 'theme.json: settings['.$index.']';

        if (! is_array($setting)) {
            return ['errors' => [$at.' must be an object.'], 'setting' => null];
        }

        $errors = [];
        $id = $setting['id'] ?? null;
        $type = is_string($setting['type'] ?? null) ? CustomThemeSettingType::tryFrom($setting['type']) : null;

        if (! is_string($id) || preg_match(self::SETTING_ID, $id) !== 1) {
            $errors[] = $at.': "id" must be lower_snake_case (a–z, 0–9, _; max 40).';
        }
        if ($type === null) {
            $errors[] = $at.': "type" must be one of '.implode(', ', CustomThemeSettingType::values()).'.';
        }

        $label = self::localized($setting['label'] ?? null, 80);
        if ($label === null) {
            $errors[] = $at.': "label" needs "en" and "ar" texts (max 80).';
        }
        $help = isset($setting['help']) ? self::localized($setting['help'], 200) : null;
        if (isset($setting['help']) && $help === null) {
            $errors[] = $at.': "help" needs "en" and "ar" texts (max 200).';
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'setting' => null];
        }

        $clean = ['id' => $id, 'type' => $type->value, 'label' => $label, 'help' => $help];

        if ($type === CustomThemeSettingType::Select) {
            $options = $setting['options'] ?? null;
            if (! is_array($options) || ! array_is_list($options) || $options === [] || count($options) > 30) {
                return ['errors' => [$at.': "options" must list 1–30 {value, label} entries.'], 'setting' => null];
            }
            foreach ($options as $option) {
                $optionLabel = is_array($option) ? self::localized($option['label'] ?? null, 80) : null;
                if (! is_array($option) || ! is_string($option['value'] ?? null) || $option['value'] === '' || mb_strlen($option['value']) > 60 || $optionLabel === null) {
                    return ['errors' => [$at.': every option needs a "value" and an en / ar "label".'], 'setting' => null];
                }
                $clean['options'][] = ['value' => $option['value'], 'label' => $optionLabel];
            }
        }

        if ($type === CustomThemeSettingType::Number) {
            $min = $setting['min'] ?? 0;
            $max = $setting['max'] ?? 100000;
            if (! is_int($min) || ! is_int($max) || $min > $max) {
                return ['errors' => [$at.': "min" / "max" must be whole numbers with min ≤ max.'], 'setting' => null];
            }
            $clean['min'] = $min;
            $clean['max'] = $max;
        }

        if ($type === CustomThemeSettingType::Text || $type === CustomThemeSettingType::Textarea) {
            $limit = $type === CustomThemeSettingType::Text ? 500 : 2000;
            $maxLength = $setting['max_length'] ?? $limit;
            if (! is_int($maxLength) || $maxLength < 1 || $maxLength > $limit) {
                return ['errors' => [sprintf('%s: "max_length" must be 1–%d.', $at, $limit)], 'setting' => null];
            }
            $clean['max_length'] = $maxLength;
        }

        $default = $setting['default'] ?? null;
        if (! array_key_exists('default', $setting) || self::coerce($clean, $default) === null) {
            return ['errors' => [$at.': "default" is required and must be a valid value for its type.'], 'setting' => null];
        }
        $clean['default'] = self::coerce($clean, $default);

        return ['errors' => [], 'setting' => $clean];
    }

    /**
     * A value for one setting, or null when it is not valid for its type.
     *
     * @param  array<string, mixed>  $setting
     */
    public static function coerce(array $setting, mixed $value): string|int|bool|null
    {
        return match (CustomThemeSettingType::tryFrom((string) ($setting['type'] ?? ''))) {
            CustomThemeSettingType::Color => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : null,
            CustomThemeSettingType::Text, CustomThemeSettingType::Textarea => is_string($value) && mb_strlen($value) <= (int) ($setting['max_length'] ?? 500) ? $value : null,
            CustomThemeSettingType::Number => self::number($setting, $value),
            CustomThemeSettingType::Select => is_string($value) && in_array($value, array_column((array) ($setting['options'] ?? []), 'value'), true) ? $value : null,
            CustomThemeSettingType::Toggle => is_bool($value) ? $value : (in_array($value, ['1', 1, 'true', 'on'], true) ? true : (in_array($value, ['0', 0, 'false', 'off', ''], true) ? false : null)),
            CustomThemeSettingType::Font => is_string($value) && StoreFont::tryFrom($value) !== null ? $value : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $setting
     */
    private static function number(array $setting, mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d{1,9}$/', $value) === 1)) {
            return null;
        }

        $value = (int) $value;

        return $value >= (int) ($setting['min'] ?? PHP_INT_MIN) && $value <= (int) ($setting['max'] ?? PHP_INT_MAX) ? $value : null;
    }

    /**
     * Stored values merged over the schema defaults (invalid / unknown values dropped).
     *
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, mixed>|null  $stored
     * @return array<string, string|int|bool>
     */
    public static function settings(?array $manifest, ?array $stored): array
    {
        $values = [];

        foreach ((array) ($manifest['settings'] ?? []) as $setting) {
            $value = array_key_exists($setting['id'], (array) $stored) ? self::coerce($setting, $stored[$setting['id']]) : null;
            $values[$setting['id']] = $value ?? $setting['default'];
        }

        return $values;
    }

    /**
     * Template-facing values: fonts become their CSS font-family stack.
     *
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, string|int|bool>  $values
     * @return array<string, string|int|bool>
     */
    public static function templateValues(?array $manifest, array $values): array
    {
        foreach ((array) ($manifest['settings'] ?? []) as $setting) {
            if ($setting['type'] === CustomThemeSettingType::Font->value && is_string($values[$setting['id']] ?? null)) {
                $values[$setting['id']] = "'".StoreFont::from($values[$setting['id']])->family()."', system-ui, sans-serif";
            }
        }

        return $values;
    }

    /**
     * Vite font entries of the font settings in use.
     *
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, string|int|bool>  $values
     * @return list<string>
     */
    public static function fontEntries(?array $manifest, array $values): array
    {
        $entries = [];

        foreach ((array) ($manifest['settings'] ?? []) as $setting) {
            if ($setting['type'] === CustomThemeSettingType::Font->value && is_string($values[$setting['id']] ?? null) && ($font = StoreFont::tryFrom($values[$setting['id']])) !== null) {
                $entries[] = $font->cssEntry();
            }
        }

        return array_values(array_unique($entries));
    }

    /**
     * @return array{en: string, ar: string}|null
     */
    private static function localized(mixed $value, int $max): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        foreach (['en', 'ar'] as $locale) {
            if (! is_string($value[$locale] ?? null) || trim($value[$locale]) === '' || mb_strlen($value[$locale]) > $max) {
                return null;
            }
        }

        return ['en' => (string) $value['en'], 'ar' => (string) $value['ar']];
    }
}
