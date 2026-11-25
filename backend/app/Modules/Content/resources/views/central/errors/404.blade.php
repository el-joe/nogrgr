{{-- 404 (Figma 127:12974, mobile 127:13076). --}}
@php
    $home = Route::has('central.home') ? route('central.home') : url('/');
    $contact = Route::has('central.contact') ? [route('central.contact'), __('content::site.common.contact_us')] : null;
@endphp
<x-central::layout :title="__('content::site.errors.404_title')" page="site-error">
    @include('content::central.errors._body', ['code' => '404', 'title' => __('content::site.errors.404_title'), 'text' => __('content::site.errors.404_text'), 'primary' => [$home, __('content::site.common.back_home')], 'secondary' => $contact])
</x-central::layout>
