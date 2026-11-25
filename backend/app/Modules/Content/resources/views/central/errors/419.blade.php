{{-- 419 page expired (Figma 127:13167): reload returns to the previous page. --}}
@php
    $home = Route::has('central.home') ? [route('central.home'), __('content::site.common.back_home')] : null;
@endphp
<x-central::layout :title="__('content::site.errors.419_title')" page="site-error">
    @include('content::central.errors._body', ['code' => '419', 'title' => __('content::site.errors.419_title'), 'text' => __('content::site.errors.419_text'), 'primary' => [url()->previous(), __('content::site.errors.reload')], 'secondary' => $home])
</x-central::layout>
