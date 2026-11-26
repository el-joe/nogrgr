{{--
    Store CMS page — Ecommet (SF-ECO-034, Figma Storefront additions 114:107865 / m 114:108055):
    breadcrumb, pages menu (desktop), article card (title, last updated, store-managed body,
    help box). The contact page is the support request layout (SF-ECO-035, 114:108109 /
    m 114:108353): form + other ways to reach us + newsletter signup.
    Data: $page (StorePage), $showSupportForm, $menu (published pages: title, url, group), $currentUrl, $seo (SeoData).
--}}
@php
    $title = (string) $page->title;
    $locale = app()->getLocale();
@endphp
<x-sf::layout :title="$title" :seo="$seo" :mobile-title="$title" :back="route('storefront.home')" page="cms-page">
    <div class="mx-auto w-full max-w-[1440px] px-4 pb-16 pt-4 md:px-[120px] md:pt-8">
        @if ($showSupportForm)
            <h1 class="mb-6 text-[28px] font-semibold leading-9 text-sf-ink">{{ __st('support.title') }}</h1>
            @include('storefront::themes.ecommet.partials.support-form', ['intro' => $page->body])
        @else
            <nav aria-label="{{ __st('pages.menu_label') }}" class="mb-6 hidden items-center gap-1.5 text-xs leading-4 md:flex">
                <a href="{{ route('storefront.home') }}" class="text-sf-muted transition-colors hover:text-sf-ink">{{ __st('pages.home') }}</a>
                <x-ui::icon name="chevron-right" :size="12" class="text-sf-muted rtl:-scale-x-100" />
                <span class="text-sf-ink" aria-current="page">{{ $title }}</span>
            </nav>
            <div class="flex items-start gap-6">
                <nav aria-label="{{ __st('pages.menu_label') }}" class="hidden w-[280px] shrink-0 flex-col gap-0.5 rounded-sf-card border border-sf-border bg-sf-surface p-2 md:flex" data-reveal>
                    @foreach ($menu as $link)
                        @php($current = $link['url'] === $currentUrl)
                        <a href="{{ $link['url'] }}" @class([
                            'rounded-sf-input px-3 py-2.5 text-sm leading-5 transition-colors',
                            'bg-sf-accent-subtle font-medium text-sf-accent' => $current,
                            'text-sf-ink hover:bg-sf-bg' => ! $current,
                        ]) @if ($current) aria-current="page" @endif>{{ $link['title'] }}</a>
                    @endforeach
                </nav>
                <article class="flex min-w-0 flex-1 flex-col gap-4 rounded-sf-card border border-sf-border bg-sf-surface p-4 md:p-10" data-reveal>
                    <h1 class="text-2xl font-semibold leading-9 text-sf-ink md:text-[28px]">{{ $title }}</h1>
                    @if ($page->updated_at)
                        <p class="text-xs leading-4 text-sf-muted">{{ __st('pages.last_updated', ['date' => $page->updated_at->locale($locale)->isoFormat('D MMM YYYY')]) }}</p>
                    @endif
                    <div class="sf-cms-body flex flex-col gap-4 text-sm leading-5 text-sf-muted [&_a]:text-sf-accent [&_a]:underline [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:leading-[26px] [&_h2]:text-sf-ink [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-sf-ink [&_li]:ms-5 [&_ol]:list-decimal [&_ol]:space-y-1 [&_strong]:font-medium [&_strong]:text-sf-ink [&_ul]:list-disc [&_ul]:space-y-1 [&>p:first-child]:text-base [&>p:first-child]:leading-6 [&>p:first-child]:text-sf-ink">
                        {!! $page->body !!}
                    </div>
                    <a href="{{ route('storefront.pages.show', \App\Modules\Storefront\Enums\SystemStorePage::Contact->value) }}" class="flex items-center gap-2.5 rounded-sf-input bg-sf-info-bg px-3.5 py-3 text-sm leading-5 text-sf-ink transition-opacity hover:opacity-90">
                        <x-ui::icon name="info" :size="18" class="shrink-0 text-sf-info" />
                        <span class="min-w-0 flex-1">{{ __st('pages.help_box') }}</span>
                    </a>
                </article>
            </div>
        @endif
    </div>
</x-sf::layout>
