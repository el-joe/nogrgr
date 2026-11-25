{{--
    Templates (Figma 12:41985, mobile 12:43584) and templates by category (12:42161 /
    12:43373): category chips + 3-column grid of template cards. Data: $seo, $themes,
    $categories, $activeCategory (?ThemeCategory).
--}}
@php
    $themes = collect($themes ?? []);
    $activeCategory ??= null;
@endphp
<x-central::layout :seo="$seo" page="site-templates">
    <section class="relative px-4 pt-6 pb-16 lg:pt-8 lg:pb-[114px]" aria-labelledby="templates-title">
        <h1 id="templates-title" class="sr-only">{{ $activeCategory ? $activeCategory->name : __('content::site.templates.heading') }}</h1>
        <div class="lg:hidden">
            <x-central::section-title :title="$activeCategory ? $activeCategory->name : __('content::site.templates.title')" as="p" />
        </div>
        @include('content::central.partials.category-chips', ['categories' => $categories ?? collect(), 'activeCategory' => $activeCategory, 'class' => 'mt-6 lg:mt-0'])
        @if ($themes->isNotEmpty())
            <ul class="mx-auto mt-6 grid max-w-[1023px] grid-cols-2 gap-3 sm:gap-6 lg:mt-14 lg:grid-cols-3">
                @foreach ($themes as $theme)
                    <li data-reveal="fade-up" data-reveal-delay="{{ ($loop->index % 3) * 80 }}"><x-central::template-card :theme="$theme" :eager="$loop->index < 3" /></li>
                @endforeach
            </ul>
        @else
            <x-central::empty icon="layers" :title="__('content::site.templates.empty_title')" :text="__('content::site.templates.empty_text')"
                :action-url="$activeCategory && Route::has('central.templates') ? route('central.templates') : null" :action-label="__('content::site.templates.empty_cta')" class="mt-10" />
        @endif
    </section>
</x-central::layout>
