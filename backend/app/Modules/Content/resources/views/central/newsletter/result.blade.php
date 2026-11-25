{{--
    Newsletter double opt-in result (WEB-015): confirmed, unsubscribed or an invalid link.
    Data: $seo, $state ('confirmed'|'unsubscribed'|'invalid').
--}}
@php
    $icons = ['confirmed' => 'check', 'unsubscribed' => 'mail', 'invalid' => 'alert-triangle'];
@endphp
<x-central::layout :seo="$seo" page="site-newsletter">
    <section class="relative px-4 pt-10 pb-20 lg:pt-[93px] lg:pb-[120px]">
        <x-central::empty
            :icon="$icons[$state] ?? 'mail'"
            :title="__('content::central.newsletter.'.$state.'_title')"
            :text="__('content::central.newsletter.'.$state.'_text')"
            :action-url="route('central.home')"
            :action-label="__('content::central.newsletter.back_home')"
        />
    </section>
</x-central::layout>
