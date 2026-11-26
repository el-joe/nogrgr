{{-- "Payment methods" card images of the cart summary (1:1450): the store's accepted badges (StorefrontShell). --}}
@php($badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges())
@if ($badges)
    <ul {{ $attributes->class(['flex flex-wrap gap-3']) }} role="list">
        @foreach ($badges as $badge)
            <li class="eco-pay-badge h-[37px] w-14 rounded border border-eco-stroke" title="{{ __st('shell.payments.'.$badge) }}">
                @switch($badge)
                    @case('visa')<span class="italic tracking-tight text-[#1a1f71]">VISA</span>@break
                    @case('mastercard')<span class="relative flex" aria-hidden="true"><span class="size-4 rounded-full bg-[#eb001b]"></span><span class="-ms-1.5 size-4 rounded-full bg-[#f79e1b]/90"></span></span><span class="sr-only">Mastercard</span>@break
                    @case('paypal')<span class="text-[#003087]">Pay<span class="text-[#009cde]">Pal</span></span>@break
                    @default<span class="px-1 text-center text-[10px] leading-tight">{{ __st('shell.payments.'.$badge) }}</span>
                @endswitch
            </li>
        @endforeach
    </ul>
@endif
