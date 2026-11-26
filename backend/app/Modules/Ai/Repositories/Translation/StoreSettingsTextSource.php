<?php

namespace App\Modules\Ai\Repositories\Translation;

use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Modules\Platform\Enums\StoreSettingKey;
use App\Modules\Platform\Repositories\Contracts\StoreSettingRepositoryContract;

/**
 * Store identity texts kept in tenant `store_settings` as {locale: text} maps: promo
 * bar, footer about / copyright and the store's return-policy text. Item key =
 * "<setting>.<path>" (e.g. "footer_settings.about"), single field "value".
 */
class StoreSettingsTextSource implements StoreTranslationSource
{
    /**
     * @var array<string, array{0: StoreSettingKey, 1: string}>
     */
    private const TEXTS = [
        'promo_bar.text' => [StoreSettingKey::PromoBar, 'text'],
        'footer_settings.about' => [StoreSettingKey::FooterSettings, 'about'],
        'footer_settings.copyright' => [StoreSettingKey::FooterSettings, 'copyright'],
        'return_policy.text' => [StoreSettingKey::ReturnPolicy, 'text'],
    ];

    public function __construct(
        private readonly StoreSettingRepositoryContract $settings,
        private readonly ContentTranslationLockRepositoryContract $locks,
    ) {}

    public function section(): StoreTranslationSection
    {
        return StoreTranslationSection::StoreSettings;
    }

    public function keys(string $locale): array
    {
        return array_values(array_filter(array_keys(self::TEXTS), fn (string $key): bool => array_filter($this->texts($key), static fn (mixed $text): bool => is_string($text) && trim($text) !== '') !== []));
    }

    public function items(array $keys, string $locale, array $retranslate = []): array
    {
        $locked = $this->locks->lockedKeys($this->section()->lockType(), $locale);
        $items = [];

        foreach ($keys as $key) {
            if (! isset(self::TEXTS[$key])) {
                continue;
            }

            $texts = $this->texts($key);
            $items[$key] = SourceTexts::item($key, ['value'], $locale, isset($retranslate[$key]),
                static fn (string $field, string $code): string => is_string($texts[$code] ?? null) ? $texts[$code] : '',
                static fn (string $field): bool => isset($locked[$key.'|'.$field]),
            );
        }

        return $items;
    }

    public function write(string $key, string $locale, array $translations): void
    {
        if (! isset(self::TEXTS[$key], $translations['value'])) {
            return;
        }

        [$setting, $path] = self::TEXTS[$key];
        $value = (array) ($this->settings->get($setting) ?? []);
        $texts = (array) ($value[$path] ?? []);
        $texts[$locale] = $translations['value'];
        $value[$path] = $texts;
        $this->settings->put($setting, $value);
    }

    public function models(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function texts(string $key): array
    {
        [$setting, $path] = self::TEXTS[$key];
        $value = $this->settings->get($setting);

        return is_array($value) && is_array($value[$path] ?? null) ? $value[$path] : [];
    }
}
