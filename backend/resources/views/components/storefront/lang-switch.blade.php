{{-- Language switch (?lang= keeps the current page). tone: light (on brand bars) | dark. --}}
@props(['tone' => 'dark', 'compact' => false])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $current = app()->getLocale();
    $next = collect(array_keys($locales))->first(fn ($code) => $code !== $current);
    // Only a same-site path survives in ?redirect= (never echo an off-site target).
    $redirect = request()->query('redirect');
    $safeRedirect = is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') && ! str_starts_with($redirect, '/\\') ? $redirect : null;
@endphp
@if ($next)
    <a href="{{ request()->fullUrlWithQuery(['lang' => $next, 'redirect' => $safeRedirect]) }}" lang="{{ $next }}" hreflang="{{ $next }}" {{ $attributes->class(['inline-flex items-center gap-1.5 text-sm', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark']) }} aria-label="{{ __st('shell.switch_language', ['language' => $locales[$next]['native']]) }}">
        <x-ui::icon name="globe" :size="18" />
        @unless ($compact)
            <span>{{ $locales[$next]['native'] }}</span>
        @endunless
    </a>
@endif
