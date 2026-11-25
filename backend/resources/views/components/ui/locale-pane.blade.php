{{-- One language pane of <x-ui::locale-tabs>; the Arabic pane is dir="rtl". --}}
@props(['parent', 'locale', 'active' => null])
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $active ??= array_key_first($locales);
@endphp
<div
    role="tabpanel"
    id="{{ $parent }}-{{ $locale }}"
    aria-labelledby="{{ $parent }}-{{ $locale }}-tab"
    data-locale-pane="{{ $locale }}"
    lang="{{ $locale }}"
    dir="{{ \App\Shared\Support\Locale::direction($locale) }}"
    @if ($locale !== $active) hidden @endif
    {{ $attributes->class(['grid gap-4']) }}
>
    {{ $slot }}
</div>
