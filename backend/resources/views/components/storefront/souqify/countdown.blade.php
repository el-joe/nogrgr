{{--
    Souqify sale countdown (core/motion/countdown.js flips the digits). style: boxes (v1 "04 Hours
    22 minutes 57 seconds" white tiles), outline (v2–v6 bordered squares, optional vertical
    stack), plain (pill digits). Days only when the sale ends later than a day.
--}}
@props(['endsAt', 'style' => 'outline', 'labels' => false, 'vertical' => false])
@php($days = $endsAt->getTimestamp() - time() > 86400)
<p {{ $attributes->class(['sq-countdown', 'sq-countdown-'.$style, 'sq-countdown-vertical' => $vertical]) }} data-countdown="{{ $endsAt->toIso8601String() }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
    @foreach (array_filter(['days' => $days, 'hours' => true, 'minutes' => true, 'seconds' => true]) as $unit => $show)
        <span class="sq-countdown-box"><span class="sq-countdown-digits" data-unit="{{ $unit }}">00</span>@if ($labels)<small>{{ __st('souqify.flash.'.$unit) }}</small>@endif</span>
    @endforeach
</p>
