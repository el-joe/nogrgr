<?php

namespace App\Modules\Storefront\DTOs;

/**
 * Head tags of one storefront page (rendered by <x-sf::seo>): exact <title>, meta
 * description, canonical on the store domain, hreflang alternates (ar / en / x-default),
 * Open Graph / Twitter and JSON-LD graphs.
 */
final readonly class SeoData
{
    /**
     * @param  array<string, string>  $alternates  hreflang => url
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public string $canonical,
        public array $alternates,
        public string $siteName,
        public string $locale,
        public ?string $image = null,
        public string $type = 'website',
        public bool $index = true,
        public array $jsonLd = [],
        public ?string $prev = null,
        public ?string $next = null,
    ) {}
}
