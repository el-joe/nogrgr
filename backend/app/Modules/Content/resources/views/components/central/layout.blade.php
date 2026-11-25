{{--
    Central marketing website shell (Figma page "Central Website" 12:41054): sticky pill
    navbar (12:42345), page content, newsletter strip + footer (12:42512 / FOOTER).
    Props: seo (App\Modules\Content\Support\SeoData|null), page (data-page hook, "site-*"
    lazy-loads resources/js/central/site.js), bodyClass (extra classes on the page wrapper),
    newsletter (show the footer newsletter strip).
--}}
@props(['seo' => null, 'page' => 'site-page', 'bodyClass' => '', 'newsletter' => false, 'title' => null])
<x-layouts.central :title="$seo?->title ?? $title">
    @if ($seo)
        <x-slot:head>
            @include('content::central.partials.seo-head', ['seo' => $seo])
        </x-slot:head>
    @endif
    <a href="#main" class="site-skip">{{ __('content::site.skip_to_content') }}</a>
    <div {{ $attributes->class(['site-page relative flex min-h-screen flex-col overflow-x-clip bg-white font-sans text-site-ink', $bodyClass]) }} data-page="{{ $page }}">
        <x-central::navbar />
        <main id="main" class="relative flex-1" tabindex="-1">
            {{ $slot }}
        </main>
        <x-central::footer :newsletter="$newsletter" />
    </div>
    <x-ui::toasts />
</x-layouts.central>
