<?php

namespace App\Modules\Storefront\DTOs\Appearance;

/**
 * Appearance › Footer (VEN-034e): short "about the store" text and copyright line per
 * language (empty = theme default), and whether the social icons show.
 */
final readonly class FooterSettingsData
{
    /**
     * @param  array<string, string>  $about
     * @param  array<string, string>  $copyright
     */
    public function __construct(
        public array $about = [],
        public array $copyright = [],
        public bool $showSocial = true,
    ) {}

    public static function fromSetting(mixed $value): self
    {
        $value = is_array($value) ? $value : [];
        $clean = static fn (mixed $lines): array => array_filter(array_map('strval', (array) $lines), static fn (string $line): bool => trim($line) !== '');

        return new self(
            about: $clean($value['about'] ?? []),
            copyright: $clean($value['copyright'] ?? []),
            showSocial: (bool) ($value['show_social'] ?? true),
        );
    }

    public function aboutFor(string $locale): ?string
    {
        return $this->about[$locale] ?? $this->about['en'] ?? null;
    }

    public function copyrightFor(string $locale): ?string
    {
        return $this->copyright[$locale] ?? $this->copyright['en'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSetting(): array
    {
        return ['about' => $this->about, 'copyright' => $this->copyright, 'show_social' => $this->showSocial];
    }
}
