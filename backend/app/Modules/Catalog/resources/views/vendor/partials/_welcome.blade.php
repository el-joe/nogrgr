{{--
    Catalog page header (Figma 12:12968 "Frame 435"): welcome title + subtitle, optional
    actions slot passed as $actions (HTML string from a @section-less include).
--}}
@php
    $store = tenant();
@endphp
<header class="flex flex-wrap items-center justify-between gap-4" data-reveal="fade-up">
    <div class="grid min-w-0 gap-1">
        <h1 class="text-[26px] font-bold leading-[1.5] text-ink sm:text-[32px]">{{ $title ?? __('catalog::vendor.welcome', ['store' => $store?->getAttribute('name')]) }}</h1>
        <p class="text-sm text-ink-muted">{{ $subtitle ?? __('catalog::vendor.welcome_subtitle') }}</p>
    </div>
    @isset($actions)
        <div class="flex w-full flex-wrap gap-3 sm:w-auto">{!! $actions !!}</div>
    @endisset
</header>
