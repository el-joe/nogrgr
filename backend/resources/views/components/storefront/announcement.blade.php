{{--
    Announcement bar template part (ADM-088, decision #120): the theme's default active
    "announcement" part above the store bar — message (current language), optional link
    (store path or https URL), tone dark | brand (theme accent) | light.
--}}
@props(['part'])
@php
    /** @var \App\Modules\Storefront\Models\ThemePart $part */
    $message = $part->setting('message');
    $link = $part->setting('link');
    $external = is_string($link) && preg_match('#^https?://#i', $link) === 1;
    $tone = in_array($part->setting('tone'), ['dark', 'brand', 'light'], true) ? $part->setting('tone') : 'dark';
@endphp
@if (is_string($message) && $message !== '')
    <div @class([
        'sf-announcement relative z-30 text-center text-[13px] leading-5',
        'bg-[#0b0b0b] text-white' => $tone === 'dark',
        'bg-sf-cta text-sf-on-cta' => $tone === 'brand',
        'border-b border-sf-border bg-sf-surface text-sf-ink' => $tone === 'light',
    ]) data-sf-announcement="{{ $tone }}">
        <p class="mx-auto max-w-[1440px] px-4 py-2 lg:px-8" dir="auto">
            @if (is_string($link) && $link !== '')
                <a href="{{ $link }}" class="underline-offset-2 transition-opacity hover:underline hover:opacity-90" @if ($external) target="_blank" rel="noopener" @endif>{{ $message }}</a>
            @else
                {{ $message }}
            @endif
        </p>
    </div>
@endif
