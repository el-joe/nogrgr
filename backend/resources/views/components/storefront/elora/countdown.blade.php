{{-- Elora flip countdown boxes (hh · mm · ss, + days when the sale ends later than a day) — core/motion/countdown.js. --}}
@props(['endsAt', 'suffix' => false])
@php($days = $endsAt->getTimestamp() - time() > 86400)
<p {{ $attributes->class(['elo-countdown']) }} data-countdown="{{ $endsAt->toIso8601String() }}" data-countdown-label="{{ __st('catalog.card.ends_in') }}">
    @if ($days)<span class="elo-countdown-box"><span data-unit="days">00</span>@if ($suffix)<small>{{ __st('elora.flash.d') }}</small>@endif</span>@endif
    <span class="elo-countdown-box"><span data-unit="hours">00</span>@if ($suffix)<small>{{ __st('elora.flash.h') }}</small>@endif</span>
    <span class="elo-countdown-box"><span data-unit="minutes">00</span>@if ($suffix)<small>{{ __st('elora.flash.m') }}</small>@endif</span>
    <span class="elo-countdown-box"><span data-unit="seconds">00</span>@if ($suffix)<small>{{ __st('elora.flash.s') }}</small>@endif</span>
</p>
