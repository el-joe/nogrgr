{{-- 500 (Figma 127:13129). --}}
@php
    $contact = Route::has('central.contact') ? [route('central.contact'), __('content::site.common.contact_us')] : null;
@endphp
<x-central::layout :title="__('content::site.errors.500_title')" page="site-error">
    @include('content::central.errors._body', ['code' => '500', 'title' => __('content::site.errors.500_title'), 'text' => __('content::site.errors.500_text'), 'primary' => [url()->current(), __('content::site.errors.try_again')], 'secondary' => $contact])
</x-central::layout>
