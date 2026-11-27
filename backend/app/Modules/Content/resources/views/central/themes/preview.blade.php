{{--
    Theme live preview (WEB-004, Figma Central Website additions 127:11426 desktop · 127:11559
    mobile view; decisions #117 a / #119). Brown top bar: back to all themes, theme name +
    "live demo" tag, Desktop / Tablet / Mobile switch, theme switcher (prev / next, list,
    swipe or drag the title), EN / AR, "Use this template" → registration with the theme
    preselected. Canvas: the real theme rendered by the read-only platform demo store in a
    device frame (iframe). No demo store yet → the theme screenshot in the frame. noindex.
    Data: $seo, $preview (ThemeShowcaseService::preview()).
--}}
@php
    $themes = $preview['themes'];
    $index = max(0, (int) array_search($preview['slug'], array_column($themes, 'slug'), true));
    $prev = $themes[($index - 1 + count($themes)) % count($themes)] ?? null;
    $next = $themes[($index + 1) % count($themes)] ?? null;
    $locales = \App\Shared\Support\Locale::enabled();
    $devices = ['desktop' => 'desktop', 'tablet' => 'tablet', 'mobile' => 'mobile'];
@endphp
<x-layouts.central :title="$seo->title">
    <x-slot:head>
        @include('content::central.partials.seo-head', ['seo' => $seo])
    </x-slot:head>
    <div class="site-tp flex min-h-dvh flex-col font-sans" data-page="theme-preview" data-theme-preview data-theme="{{ $preview['slug'] }}">
        <header class="site-tp__bar sticky top-0 z-20 bg-site-ink text-white">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 lg:h-[72px] lg:flex-nowrap lg:px-6 lg:py-0">
                <a href="{{ route('central.themes') }}" class="site-tp__back inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 pe-2 text-sm font-medium text-white/90 transition-colors hover:text-white">
                    <x-ui::icon name="chevron-left" :size="18" class="rtl:-scale-x-100" />
                    <span>{{ __('content::theme_preview.preview.all') }}</span>
                </a>
                <span class="hidden h-6 w-px bg-white/20 sm:block" aria-hidden="true"></span>

                <div class="site-tp__title flex min-w-0 flex-1 items-center gap-2 lg:flex-none" data-tp-swipe>
                    @if ($prev)
                        <a href="{{ $prev['url'] }}" class="site-tp__step grid size-8 shrink-0 place-items-center rounded-full text-white/80 hover:bg-white/10 hover:text-white" rel="prev" data-tp-prev aria-label="{{ __('content::theme_preview.preview.previous', ['theme' => $prev['name']]) }}">
                            <x-ui::icon name="chevron-left" :size="16" class="rtl:-scale-x-100" />
                        </a>
                    @endif
                    <div class="relative min-w-0" data-tp-menu>
                        <button type="button" class="flex min-w-0 items-center gap-2 rounded-full px-2 py-1 text-start hover:bg-white/10" aria-haspopup="listbox" aria-expanded="false" data-tp-menu-trigger aria-label="{{ __('content::theme_preview.preview.switch') }}">
                            <h1 class="truncate text-base font-semibold sm:text-lg">{{ $preview['name'] }}</h1>
                            <x-ui::icon name="chevron-down" :size="16" class="shrink-0 text-white/80" />
                        </button>
                        <ul class="site-tp__menu absolute start-0 top-full z-30 mt-2 hidden max-h-[60vh] w-64 overflow-y-auto rounded-2xl bg-white p-1.5 text-site-ink shadow-xl" role="listbox" aria-label="{{ __('content::theme_preview.preview.switch') }}" data-tp-menu-list>
                            @foreach ($themes as $item)
                                <li role="option" aria-selected="{{ $item['slug'] === $preview['slug'] ? 'true' : 'false' }}">
                                    <a href="{{ $item['url'] }}" class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm hover:bg-site-tint {{ $item['slug'] === $preview['slug'] ? 'font-semibold text-primary' : '' }}">
                                        {{ $item['name'] }}
                                        @if ($item['slug'] === $preview['slug'])
                                            <x-ui::icon name="check" :size="16" />
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @if ($next)
                        <a href="{{ $next['url'] }}" class="site-tp__step grid size-8 shrink-0 place-items-center rounded-full text-white/80 hover:bg-white/10 hover:text-white" rel="next" data-tp-next aria-label="{{ __('content::theme_preview.preview.next', ['theme' => $next['name']]) }}">
                            <x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" />
                        </a>
                    @endif
                    <span class="hidden shrink-0 rounded-full bg-primary px-2.5 py-0.5 text-xs font-medium text-white xl:inline">{{ __('content::theme_preview.preview.tag') }}</span>
                </div>

                <div class="site-tp__devices order-last mx-auto hidden rounded-xl bg-white/10 p-1 sm:flex lg:order-none" role="group" aria-label="{{ __('content::theme_preview.preview.device_label') }}">
                    @foreach ($devices as $device => $label)
                        <button type="button" class="site-tp__device rounded-lg px-3.5 py-1.5 text-sm font-medium" data-tp-device="{{ $device }}" aria-pressed="{{ $device === 'desktop' ? 'true' : 'false' }}">{{ __('content::theme_preview.preview.devices.'.$label) }}</button>
                    @endforeach
                </div>

                <div class="ms-auto flex shrink-0 items-center gap-2 sm:gap-3">
                    @if (count($locales) > 1)
                        <div class="flex rounded-full bg-white/10 p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('content::theme_preview.preview.language') }}">
                            @foreach ($locales as $code => $language)
                                <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" hreflang="{{ $code }}" lang="{{ $code }}" class="rounded-full px-2.5 py-1 uppercase {{ app()->getLocale() === $code ? 'bg-white text-site-ink' : 'text-white/80 hover:text-white' }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $code }}</a>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ $preview['registerUrl'] }}" class="site-tp__cta inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-hover sm:h-11 sm:px-5 sm:text-base" data-tp-cta>
                        {{ __('content::theme_preview.preview.cta') }}
                        <x-ui::icon name="chevron-right" :size="18" class="rtl:-scale-x-100" />
                    </a>
                </div>
            </div>
        </header>

        <main id="main" class="site-tp__canvas flex flex-1 justify-center px-3 py-4 sm:px-6 sm:py-8" tabindex="-1">
            <div class="site-tp__frame relative w-full overflow-hidden bg-white" data-tp-frame data-device="desktop">
                @if ($preview['frameUrl'])
                    <div class="site-tp__loading absolute inset-0 z-10 grid place-items-center bg-white" data-tp-loading role="status">
                        <span class="flex items-center gap-3 text-sm text-site-gray">
                            <span class="site-tp__spinner size-5 rounded-full border-2 border-site-tint border-t-primary" aria-hidden="true"></span>
                            {{ __('content::theme_preview.preview.loading', ['theme' => $preview['name']]) }}
                        </span>
                    </div>
                    <iframe src="{{ $preview['frameUrl'] }}" title="{{ __('content::theme_preview.preview.frame_title', ['theme' => $preview['name']]) }}" class="size-full border-0" data-tp-iframe referrerpolicy="strict-origin-when-cross-origin"></iframe>
                @else
                    <div class="site-template relative size-full overflow-hidden" tabindex="0" aria-label="{{ $preview['name'] }}">
                        @if ($preview['image'])
                            <img src="{{ $preview['image'] }}" alt="{{ __('content::site.templates.preview_alt', ['name' => $preview['name']]) }}" class="site-template__shot absolute inset-x-0 top-0 h-auto w-full" width="650" height="1600" decoding="async">
                        @endif
                        <p class="absolute inset-x-4 bottom-4 rounded-xl bg-site-ink/90 px-4 py-3 text-center text-sm text-white">{{ __('content::theme_preview.preview.no_demo') }}</p>
                    </div>
                @endif
            </div>
        </main>
    </div>
</x-layouts.central>
