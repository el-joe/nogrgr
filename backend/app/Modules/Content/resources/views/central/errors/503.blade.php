{{-- 503 maintenance (Figma 127:13205): gear disc, "back at" time from $retryAfter (?Carbon). --}}
@php
    $retryAfter ??= null;
    $appName = config('app.name');
    $text = $retryAfter
        ? __('content::site.errors.503_text', ['app' => $appName, 'time' => $retryAfter->copy()->locale(app()->getLocale())->translatedFormat('H:i T')])
        : __('content::site.errors.503_text_generic', ['app' => $appName]);
@endphp
<x-central::layout :title="__('content::site.errors.503_title')" page="site-error">
    @include('content::central.errors._body', ['icon' => 'settings', 'title' => __('content::site.errors.503_title'), 'text' => $text, 'primary' => [url()->current(), __('content::site.errors.check_status')]])
</x-central::layout>
