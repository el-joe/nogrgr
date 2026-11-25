<?php

namespace App\Modules\Content\Support;

/**
 * Head metadata of one central website page (rendered by content::central.partials.seo-head):
 * title (without the site name; the document layout appends it), description, canonical
 * + hreflang alternates, Open Graph / Twitter image and type, JSON-LD blocks and breadcrumbs.
 */
final class SeoData
{
    /**
     * @param  array<string, string>  $alternates  hreflang => absolute URL (incl. x-default)
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  list<array{name: string, url: string|null}>  $breadcrumbs
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly array $alternates = [],
        public readonly ?string $image = null,
        public readonly string $type = 'website',
        public readonly array $jsonLd = [],
        public readonly array $breadcrumbs = [],
        public readonly bool $noindex = false,
        public readonly ?string $publishedTime = null,
        public readonly ?string $modifiedTime = null,
    ) {}

    public function fullTitle(): string
    {
        $site = (string) config('app.name');

        return $this->title === '' || $this->title === $site ? $site : $this->title.' · '.$site;
    }
}
