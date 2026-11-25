{{--
    Language switcher (Figma 12:42481): globe + current language ("En" / "ع"); links to
    the other enabled locale via route('central.locale'). Hidden when only one locale.
--}}
@props(['compact' => false])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $current = app()->getLocale();
    $next = collect(array_keys($locales))->first(fn (string $code): bool => $code !== $current);
    $short = fn (string $code): string => $code === 'ar' ? 'ع' : \Illuminate\Support\Str::ucfirst($code);
    $url = $next ? (\Illuminate\Support\Facades\Route::has('central.locale') ? route('central.locale', ['locale' => $next]) : request()->fullUrlWithQuery(['lang' => $next])) : null;
@endphp
@if ($url)
    <a href="{{ $url }}" {{ $attributes->class(['site-lang group inline-flex items-center gap-2 text-site-gray transition-colors hover:text-site-ink', 'text-lg' => $compact, 'text-xl' => ! $compact]) }}
        hreflang="{{ $next }}" rel="alternate" aria-label="{{ __('content::site.nav.switch_language', ['language' => $locales[$next]['native'] ?? $next]) }}">
        <x-ui::icon name="globe" :size="24" class="transition-transform duration-500 group-hover:rotate-[30deg]" />
        <span>{{ $short($current) }}</span>
    </a>
@endif
