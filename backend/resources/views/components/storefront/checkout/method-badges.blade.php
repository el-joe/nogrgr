{{-- Payment method chips (spec 08 §1, old components/payment-method-badges): size xs (16 px) | sm (20 px). --}}
@props(['methods' => [], 'size' => 'sm'])
@php($badges = \App\Modules\Storefront\Support\PaymentMethodBadges::resolve($methods))
@if ($badges)
    <div {{ $attributes->class(['flex flex-wrap items-center gap-1']) }}>
        @foreach ($badges as $badge)
            <span @class(['inline-flex items-center gap-[3px] whitespace-nowrap rounded-[6px] border border-[#e5e7eb] bg-white px-[7px] py-0.5 text-[10.5px] font-semibold text-[#374151]', 'h-4' => $size === 'xs', 'h-5' => $size !== 'xs']) title="{{ $badge['label'] }}" dir="ltr">
                <x-sf::checkout.method-icon :icon="$badge['icon']" />{{ $badge['label'] }}
            </span>
        @endforeach
    </div>
@endif
