{{--
    Store logo: the tenant's own logo when set, otherwise its name as a wordmark
    (never the theme's demo brand). tone: dark (on light bars) | light (on dark/brand bars).
--}}
@props(['tone' => 'dark', 'size' => 'md'])
@php
    $name = \App\Modules\Storefront\Support\StorefrontTheme::storeName();
    $logo = \App\Modules\Storefront\Support\StorefrontTheme::logoUrl();
    $home = Route::has('storefront.home') ? route('storefront.home') : null;
    $text = ['sm' => 'text-lg', 'md' => 'text-2xl', 'lg' => 'text-3xl'][$size] ?? 'text-2xl';
@endphp
<a @if ($home) href="{{ $home }}" @endif {{ $attributes->class(['inline-flex shrink-0 items-center', 'pointer-events-none' => ! $home]) }} aria-label="{{ $name }}">
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $name }}" class="h-8 w-auto max-w-[160px] object-contain" height="32">
    @else
        <span @class(['font-semibold tracking-tight', $text, 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark', 'text-sf-accent' => $tone === 'accent'])>{{ $name }}</span>
    @endif
</a>
