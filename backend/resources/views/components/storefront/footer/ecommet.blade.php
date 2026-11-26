{{--
    Ecommet footer (Figma 1:573 desktop, m 1:2656 bottom): charcoal block with link columns,
    store logo, "We accept" payment badges; grey legal bar; dark copyright bar. Phones stack
    the columns with dividers.
--}}
@props(['columns' => [], 'legal' => [], 'name'])
@php($badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges())
<footer class="eco-footer mt-auto" aria-label="{{ __st('shell.footer.label') }}">
    <div class="eco-container grid gap-6 pb-8 pt-8 lg:grid-cols-[1fr_auto] lg:gap-10 lg:pb-6 lg:pt-12">
        <div class="grid gap-5 lg:gap-10">
            @if ($columns)
                <div class="grid gap-5 sm:grid-cols-3 lg:gap-10">
                    @foreach ($columns as $key => $links)
                        <div class="grid content-start gap-2 lg:gap-4" data-reveal="fade-up">
                            <h2 class="text-base font-medium">{{ __st('shell.footer.columns.'.$key) }}</h2>
                            @foreach ($links as $link)
                                <a href="{{ $link['url'] }}" class="w-fit text-sm tracking-[0.5px] text-white/90">{{ $link['title'] }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($badges)
                <div class="grid gap-3 border-t border-white/20 pt-5 lg:border-0 lg:pt-0">
                    <h2 class="text-base font-medium">{{ __st('shell.footer.we_accept') }}</h2>
                    <ul class="flex flex-wrap gap-2.5" role="list">
                        @foreach ($badges as $badge)
                            <li class="eco-pay-badge" title="{{ __st('shell.payments.'.$badge) }}">
                                @switch($badge)
                                    @case('visa')<span class="italic tracking-tight text-[#1a1f71]">VISA</span>@break
                                    @case('mastercard')<span class="relative flex" aria-hidden="true"><span class="size-4 rounded-full bg-[#eb001b]"></span><span class="-ms-1.5 size-4 rounded-full bg-[#f79e1b]/90"></span></span><span class="sr-only">Mastercard</span>@break
                                    @case('paypal')<span class="text-[#003087]">Pay<span class="text-[#009cde]">Pal</span></span>@break
                                    @default<span class="px-1 text-center text-[10px] leading-tight">{{ __st('shell.payments.'.$badge) }}</span>
                                @endswitch
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="grid content-start gap-4 border-t border-white/20 pt-5 lg:min-w-[190px] lg:border-0 lg:pt-0">
            <x-sf::logo tone="light" />
            <x-sf::locale-button class="w-fit" />
        </div>
    </div>
    @if ($legal)
        <div class="bg-sf-footer-bar">
            <nav class="eco-container flex flex-wrap justify-center gap-x-10 gap-y-2 py-3 text-sm lg:gap-x-20" aria-label="{{ __st('shell.footer.legal') }}">
                @foreach ($legal as $link)<a href="{{ $link['url'] }}">{{ $link['title'] }}</a>@endforeach
            </nav>
        </div>
    @endif
    <p class="bg-sf-footer-bottom py-4 text-center text-xs text-white/80 lg:text-sm lg:text-white">{{ __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
</footer>
