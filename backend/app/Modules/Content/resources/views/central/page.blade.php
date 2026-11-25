{{--
    Legal / static page (Figma Terms 12:41891, Privacy 127:11603 (m 127:11737), Static
    page 127:11792): tinted header card (title + last updated), sticky "On this page"
    table of contents built from the body's h2s (JS; hidden with < 2 headings) and the
    admin-edited body. Data: $seo, $page (Page), $kind ('terms'|'privacy'|'page').
--}}
@php
    $updated = $page->updated_at?->locale(app()->getLocale())->translatedFormat('d M Y');
    $hasBody = filled(trim(strip_tags((string) $page->body)));
@endphp
<x-central::layout :seo="$seo" page="site-page-{{ $kind ?? 'page' }}">
    <span class="site-glow site-glow--page" aria-hidden="true"></span>
    <div class="relative mx-auto max-w-[1264px] px-4 pt-6 pb-16 lg:px-[100px] lg:pt-[33px] lg:pb-[72px]">
        <header class="rounded-3xl bg-site-tint px-6 py-8 text-center lg:py-[48px]" data-reveal="fade-up">
            <h1 class="text-2xl font-bold text-site-ink lg:text-[40px]">{{ $page->title }}</h1>
            @if ($updated)
                <p class="mt-2 text-xs text-site-gray">{{ __('content::site.page.updated', ['date' => $updated]) }}</p>
            @endif
            <x-central::breadcrumbs :items="$seo->breadcrumbs ?? []" class="mt-3 flex justify-center" />
        </header>
        @if ($hasBody)
            <div class="mt-6 lg:mt-8 lg:flex lg:items-start lg:gap-8">
                <aside class="mb-6 lg:sticky lg:top-28 lg:mb-0 lg:w-[223px] lg:shrink-0" data-toc-wrap>
                    <nav class="rounded-3xl bg-white p-5 text-xs text-site-gray shadow-[0_8px_30px_rgba(65,37,30,0.08)]" data-toc="#page-body" aria-labelledby="toc-title" hidden>
                        <p id="toc-title" class="mb-2 text-sm font-medium text-site-ink">{{ __('content::site.page.on_this_page') }}</p>
                        <ol class="site-toc flex flex-col gap-1" data-toc-list></ol>
                    </nav>
                </aside>
                <article id="page-body" class="site-prose site-prose--lg min-w-0 flex-1" data-reveal="fade-up" data-reveal-delay="100">{!! $page->body !!}</article>
            </div>
        @else
            <x-central::empty icon="file" :title="$page->title" :text="__('content::site.page.empty')"
                :action-url="Route::has('central.contact') ? route('central.contact') : null" :action-label="__('content::site.common.contact_us')" />
        @endif
    </div>
</x-central::layout>
