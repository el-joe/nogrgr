{{--
    Template category chips (Figma Categories 12:41473): orange "All" pill + draggable
    rail of #F3F3F3/47% pills (Md-14 #7C6F6F, tracking .5) + arrow that pages the rail.
    Links to central.templates / central.templates.category.
    Inputs: $categories (Collection<ThemeCategory>), $activeCategory (?ThemeCategory), $class.
--}}
@php
    $hasCategoryRoute = \Illuminate\Support\Facades\Route::has('central.templates.category');
    $allUrl = \Illuminate\Support\Facades\Route::has('central.templates') ? route('central.templates') : null;
    $activeSlug = $activeCategory?->slug;
@endphp
@if (collect($categories)->isNotEmpty() && $hasCategoryRoute)
    <nav class="mx-auto flex max-w-[812px] items-center gap-3 ps-4 lg:ps-0 {{ $class ?? '' }}" aria-label="{{ __('content::site.templates.filter') }}" data-reveal="fade-up">
        @if ($allUrl)
            <a href="{{ $allUrl }}" @class(['site-chip shrink-0 px-8 text-base', 'is-active' => $activeSlug === null]) @if ($activeSlug === null) aria-current="page" @endif>{{ __('content::site.templates.all') }}</a>
        @endif
        <div class="swiper site-chips min-w-0 flex-1" data-chips>
            <ul class="swiper-wrapper">
                @foreach ($categories as $category)
                    @php $active = $activeSlug === $category->slug; @endphp
                    <li class="swiper-slide !w-auto">
                        <a href="{{ route('central.templates.category', ['slug' => $category->slug]) }}" @class(['site-chip px-6 text-sm font-medium tracking-[0.5px]', 'is-active' => $active]) @if ($active) aria-current="page" @endif draggable="false">{{ $category->name }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="hidden size-6 shrink-0 place-items-center text-primary transition-transform hover:translate-x-0.5 disabled:opacity-30 lg:grid rtl:hover:-translate-x-0.5" data-chips-next aria-label="{{ __('content::site.templates.scroll') }}">
            <x-ui::icon name="chevron-right" :size="24" class="rtl:-scale-x-100" />
        </button>
    </nav>
@endif
