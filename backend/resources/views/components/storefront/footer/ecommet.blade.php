{{--
    Ecommet footer (Figma 1:573 desktop, m 1:2656 bottom): charcoal block with link columns,
    store logo, "We accept" payment badges; grey legal bar; dark copyright bar. Phones stack
    the columns with dividers.
--}}
@props(['columns' => [], 'legal' => [], 'name', 'copyright' => null])
@php
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges(wallets: true);
    $appearance = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class);
    $about = $appearance->aboutText();
    $social = $appearance->socialLinks();
@endphp
<footer class="eco-footer mt-auto" aria-label="{{ __st('shell.footer.label') }}">
    <div class="eco-container grid gap-6 pb-8 pt-8 lg:grid-cols-[minmax(0,1013px)_auto] lg:justify-between lg:gap-[70px] lg:pb-10 lg:pt-[49px]">
        <div class="grid content-start gap-5 lg:gap-12">
            @if ($columns)
                <div class="grid gap-5 sm:grid-cols-3 lg:justify-between lg:gap-6 lg:[grid-template-columns:repeat(3,220px)]">
                    @foreach ($columns as $key => $links)
                        <div class="grid content-start gap-2 lg:gap-[26px]" data-reveal="fade-up">
                            <h2 class="text-base font-semibold leading-[normal]">{{ __st('shell.footer.columns.'.$key) }}</h2>
                            <div class="grid gap-2 lg:gap-4">
                                @foreach ($links as $link)
                                    <a href="{{ $link['url'] }}" class="w-fit text-sm leading-[normal] tracking-[0.5px]">{{ $link['title'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($badges)
                <div class="grid gap-3 border-t border-white/20 pt-5 lg:border-0 lg:pt-0" data-reveal="fade-up">
                    <h2 class="text-base font-semibold leading-[normal]">{{ __st('shell.footer.we_accept') }}</h2>
                    <ul class="flex flex-wrap gap-3" role="list">
                        @foreach ($badges as $badge)
                            <li class="eco-pay-badge" title="{{ __st('shell.payments.'.$badge) }}">
                                @if (in_array($badge, ['visa', 'mastercard', 'applepay', 'fawry'], true))
                                    {{-- Real card images from the Figma footer (1:610), 56 x 37. --}}
                                    <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/payments/'.$badge.'.png') }}" alt="{{ __st('shell.payments.'.$badge) }}" width="56" height="37" loading="lazy" decoding="async">
                                @else
                                    {{-- No Figma image (PayPal, Tabby, Tamara, COD, ...): the method name in the same white box. --}}
                                    @if ($badge === 'paypal')<span class="text-[#003087]">Pay<span class="text-[#009cde]">Pal</span></span>@else<span class="line-clamp-2 max-w-full overflow-hidden break-words px-0.5 text-center text-[9px] leading-[1.1]">{{ __st('shell.payments.'.$badge) }}</span>@endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="grid content-start gap-4 border-t border-white/20 pt-5 lg:min-w-[190px] lg:gap-8 lg:border-0 lg:pt-0" data-reveal="fade-up">
            <x-sf::logo tone="light" />
            @if ($about)
                <p class="max-w-[320px] text-sm leading-6 tracking-[0.5px] text-white/80">{{ $about }}</p>
            @endif
            @if ($social)
                <div class="grid gap-4">
                    <h2 class="text-base font-medium leading-[normal]">{{ __st('shell.footer.connect', ['name' => $name]) }}</h2>
                    <ul class="flex flex-wrap items-center gap-4" role="list" aria-label="{{ __st('appearance.follow_us') }}">
                        @foreach ($social as $profile)
                            <li>
                                <a href="{{ $profile['url'] }}" target="_blank" rel="noopener me" class="eco-social grid size-6 place-items-center" aria-label="{{ $profile['label'] }}" title="{{ $profile['label'] }}">
                                    <x-ui::icon :name="$profile['icon']" :size="24" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
    @if ($legal)
        <div class="eco-footer-bar">
            <nav class="eco-container flex min-h-12 flex-wrap items-center justify-center gap-x-10 gap-y-2 py-3 text-sm leading-[normal] tracking-[0.5px] lg:gap-x-[88px] lg:py-0" aria-label="{{ __st('shell.footer.legal') }}">
                @foreach ($legal as $link)<a href="{{ $link['url'] }}">{{ $link['title'] }}</a>@endforeach
            </nav>
        </div>
    @endif
    <p class="eco-footer-bottom">{{ $copyright ?? __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
</footer>
{{-- One variant picker per Ecommet page, shared by every card's cart button. --}}
<x-sf::variant-picker />
