{{--
    Souqify footer (Figma 1:81429; v1 navy #000C20 with a blue link bar, v2–v6 grey #3B3B3B —
    colours from the variant tokens). Store logo / name + tagline, link columns (store pages +
    catalog links), "We accept" badges (the store's gateways), legal bar, copyright. The
    Figma's ELORA logo is not used (the tenant's own logo is shown); the social icons are the
    store's own profiles (Appearance › Social links, VEN-034f).
--}}
@props(['columns' => [], 'legal' => [], 'name', 'copyright' => null])
<footer class="sq-footer mt-auto text-white" aria-label="{{ __st('shell.footer.label') }}">
    <div class="mx-auto grid max-w-[1440px] gap-10 px-8 py-12 max-lg:px-5 max-lg:py-8 lg:grid-cols-[minmax(0,300px)_minmax(0,1fr)_auto]">
        <div class="grid content-start gap-4">
            <x-sf::logo :tone="app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme()->variant > 1 ? 'light' : 'accent'" size="md" />
            <p class="max-w-[280px] text-sm leading-6 text-white/80">{{ __st('souqify.footer.tagline') }}</p>
            <x-sf::footer.social />
        </div>
        @if ($columns)
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-3">
                @foreach ($columns as $key => $links)
                    <div class="grid content-start gap-4">
                        <h2 class="text-lg font-medium">{{ __st('shell.footer.columns.'.$key) }}</h2>
                        <ul class="grid gap-3" role="list">
                            @foreach ($links as $link)<li><a href="{{ $link['url'] }}" class="text-sm text-white/70 transition hover:text-white">{{ $link['title'] }}</a></li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="grid content-start gap-3">
            <p class="text-sm font-medium">{{ __st('souqify.footer.we_accept') }}</p>
            <x-sf::cart.pay-badges class="sq-footer-badges" />
        </div>
    </div>
    @if ($legal)
        <nav class="sq-footer-bar" aria-label="{{ __st('shell.footer.legal') }}">
            <div class="mx-auto flex max-w-[1440px] flex-wrap justify-center gap-x-24 gap-y-2 px-6 py-3 text-sm max-lg:gap-x-8">
                @foreach ($legal as $link)<a href="{{ $link['url'] }}" class="hover:underline">{{ $link['title'] }}</a>@endforeach
            </div>
        </nav>
    @endif
    <div class="sq-footer-bottom py-4 text-center text-sm max-lg:pb-24">
        <p>{{ $copyright ?? __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
    </div>
</footer>
