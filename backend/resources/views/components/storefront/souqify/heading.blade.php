{{--
    Souqify section heading: bold title at the start, "view all products →" (v1) or "see all"
    (v2–v6) at the end. tone: dark (on the page) | light (on a coloured band). center: the
    v2 / v3 "Shop by Category" / Best Seller title centred without a link.
--}}
@props(['title', 'more' => null, 'tone' => 'dark', 'center' => false, 'id' => null])
@php($v1 = app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme()->variant <= 1)
<div {{ $attributes->class(['sq-heading', 'sq-heading-light' => $tone === 'light', 'justify-center text-center' => $center]) }} data-reveal="fade-up">
    <h2 @if ($id) id="{{ $id }}" @endif class="sq-heading-title">{{ $title }}</h2>
    @if ($more && ! $center)
        <a href="{{ $more }}" class="sq-see-all">{{ $v1 ? __st('souqify.home.view_all') : __st('souqify.home.see_all') }}@if ($v1)<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" />@endif</a>
    @endif
</div>
