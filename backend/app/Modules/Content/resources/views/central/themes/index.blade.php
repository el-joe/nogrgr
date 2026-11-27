{{--
    Theme gallery (WEB-004, decisions #117 a / #119): the 13 active catalogue themes as
    separate themes — preview screenshot (scrolls on hover / focus like the Templates card
    12:41069), name, description, "Live preview" (device-frame preview) and "Start with this
    theme" (registration with the theme preselected). Data: $seo, $themes, $registerUrls.
--}}
@php
    $themes = collect($themes ?? []);
@endphp
<x-central::layout :seo="$seo" page="site-themes">
    <section class="relative px-4 pt-8 pb-16 lg:pt-14 lg:pb-[114px]" aria-labelledby="themes-title">
        <x-central::section-title id="themes-title" as="h1" :title="__('content::theme_preview.gallery.heading')" :text="__('content::theme_preview.gallery.intro')">
            <span class="rounded-full bg-site-tint px-3 py-1 text-sm font-medium text-primary">{{ trans_choice('content::theme_preview.gallery.count', $themes->count(), ['count' => $themes->count()]) }}</span>
        </x-central::section-title>

        @if ($themes->isNotEmpty())
            <ul class="mx-auto mt-8 grid max-w-[1100px] gap-6 sm:grid-cols-2 lg:mt-14 lg:grid-cols-3">
                @foreach ($themes as $theme)
                    @php
                        $relative = 'resources/images/central/themes/'.$theme->slug.'.webp';
                        $image = is_file(base_path($relative)) ? \Illuminate\Support\Facades\Vite::asset($relative) : $theme->preview_url;
                    @endphp
                    <li class="site-theme-card flex flex-col overflow-hidden rounded-2xl border border-site-border bg-white" data-reveal="fade-up" data-reveal-delay="{{ ($loop->index % 3) * 80 }}" data-theme-card="{{ $theme->slug }}">
                        <a href="{{ route('central.themes.show', ['slug' => $theme->slug]) }}" class="site-template relative block aspect-[325/240] overflow-hidden border-b border-site-border bg-site-tint" aria-label="{{ __('content::theme_preview.gallery.preview_named', ['theme' => $theme->name]) }}">
                            @if ($image)
                                <img src="{{ $image }}" alt="{{ __('content::site.templates.preview_alt', ['name' => $theme->name]) }}" width="650" height="1600"
                                    class="site-template__shot pointer-events-none absolute inset-x-0 top-0 h-auto w-full select-none" @if ($loop->index < 3) fetchpriority="high" @else loading="lazy" @endif decoding="async" draggable="false">
                            @else
                                <span class="grid h-full place-items-center text-primary"><x-ui::icon name="layers" :size="40" /></span>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col gap-2 p-5">
                            <h2 class="text-lg font-medium text-site-ink">{{ $theme->name }}</h2>
                            @if (filled($theme->description))
                                <p class="line-clamp-3 text-sm text-site-gray">{{ $theme->description }}</p>
                            @endif
                            <div class="mt-auto flex flex-wrap gap-2 pt-3">
                                <a href="{{ route('central.themes.show', ['slug' => $theme->slug]) }}" class="inline-flex h-10 items-center gap-1.5 rounded-[40px] border border-site-border px-4 text-sm font-medium text-site-ink transition-colors hover:border-primary hover:text-primary">
                                    <x-ui::icon name="eye" :size="16" />{{ __('content::theme_preview.gallery.preview') }}
                                </a>
                                <a href="{{ $registerUrls[$theme->slug] ?? '#' }}" class="inline-flex h-10 items-center rounded-[40px] bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-primary-hover" data-theme-start="{{ $theme->slug }}">
                                    {{ __('content::theme_preview.preview.cta') }}
                                </a>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <x-central::empty icon="layers" :title="__('content::theme_preview.gallery.empty_title')" :text="__('content::theme_preview.gallery.empty_text')"
                :action-url="route('central.home')" :action-label="__('content::theme_preview.gallery.empty_cta')" class="mt-10" />
        @endif
    </section>
</x-central::layout>
