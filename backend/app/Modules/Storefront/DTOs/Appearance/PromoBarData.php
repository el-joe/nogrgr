<?php

namespace App\Modules\Storefront\DTOs\Appearance;

/**
 * Appearance › Promo bar (VEN-034c): a one-line message above every theme header, per
 * language, with an optional link and its own background colour.
 */
final readonly class PromoBarData
{
    public const DEFAULT_BACKGROUND = '#0b0b0b';

    /**
     * @param  array<string, string>  $text  locale => message
     */
    public function __construct(
        public bool $enabled = false,
        public array $text = [],
        public ?string $link = null,
        public string $background = self::DEFAULT_BACKGROUND,
    ) {}

    public static function fromSetting(mixed $value): self
    {
        $value = is_array($value) ? $value : [];
        $background = $value['background'] ?? null;
        $link = $value['link'] ?? null;

        return new self(
            enabled: (bool) ($value['enabled'] ?? false),
            text: array_filter(array_map('strval', (array) ($value['text'] ?? [])), static fn (string $line): bool => trim($line) !== ''),
            link: is_string($link) && $link !== '' ? $link : null,
            background: is_string($background) && preg_match('/^#[0-9a-f]{6}$/i', $background) === 1 ? strtolower($background) : self::DEFAULT_BACKGROUND,
        );
    }

    /**
     * Message in the locale (English, then any filled language, as fallback).
     */
    public function textFor(string $locale): ?string
    {
        return $this->text[$locale] ?? $this->text['en'] ?? (array_values($this->text)[0] ?? null);
    }

    public function isVisible(): bool
    {
        return $this->enabled && $this->text !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSetting(): array
    {
        return ['enabled' => $this->enabled, 'text' => $this->text, 'link' => $this->link, 'background' => $this->background];
    }
}
