<?php

namespace App\Modules\Content\Support;

use App\Shared\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Builds SeoData for central pages: canonical + hreflang URLs carry `?lang=` (the
 * locale switch of SetLocale) and the page number; every page gets the Organization
 * JSON-LD, plus BreadcrumbList when breadcrumbs are given.
 */
final class CentralSeo
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  list<array{name: string, url: string|null}>  $breadcrumbs  without "Home"
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function make(
        string $title,
        string $description,
        array $breadcrumbs = [],
        array $jsonLd = [],
        ?string $image = null,
        string $type = 'website',
        bool $noindex = false,
        ?string $publishedTime = null,
        ?string $modifiedTime = null,
    ): SeoData {
        $locale = app()->getLocale();
        $alternates = [];

        foreach (array_keys(Locale::enabled()) as $code) {
            $alternates[$code] = $this->localizedUrl($code);
        }

        $alternates['x-default'] = $this->localizedUrl((string) config('app.locale', 'en'));

        $trail = $breadcrumbs === [] ? [] : [['name' => __('content::central.home'), 'url' => route('central.home')], ...$breadcrumbs];
        $blocks = [$this->organization(), ...$jsonLd];

        if ($trail !== []) {
            $blocks[] = $this->breadcrumbList($trail);
        }

        return new SeoData(
            title: $title,
            description: Str::limit(trim(strip_tags($description)), 160, '…'),
            canonical: $this->localizedUrl($locale),
            alternates: $alternates,
            image: $image ?? $this->defaultImage(),
            type: $type,
            jsonLd: $blocks,
            breadcrumbs: $trail,
            noindex: $noindex,
            publishedTime: $publishedTime,
            modifiedTime: $modifiedTime,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => (string) config('app.name'),
            'url' => route('central.home'),
            'logo' => $this->defaultImage(),
            'email' => config('mail.from.address') ?: null,
        ]);
    }

    /**
     * @param  list<array{question: string, answer: string}>  $items
     * @return array<string, mixed>
     */
    public function faqPage(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($item['answer']))],
            ], $items),
        ];
    }

    private function localizedUrl(string $locale): string
    {
        $query = array_filter([
            'lang' => $locale,
            'page' => ($page = (int) $this->request->query('page', 1)) > 1 ? $page : null,
            'category' => is_string($this->request->query('category')) ? $this->request->query('category') : null,
        ]);

        $base = $this->request->path() === '/' ? rtrim($this->request->url(), '/').'/' : $this->request->url();

        return $base.'?'.http_build_query($query);
    }

    /**
     * @param  list<array{name: string, url: string|null}>  $trail
     * @return array<string, mixed>
     */
    private function breadcrumbList(array $trail): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(fn (array $crumb, int $index): array => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'] ?? $this->request->url(),
            ]), $trail, array_keys($trail))),
        ];
    }

    private function defaultImage(): ?string
    {
        $path = 'resources/images/central/og-default.webp';

        return is_file(base_path($path)) ? Vite::asset($path) : null;
    }
}
