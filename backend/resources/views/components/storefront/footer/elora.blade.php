{{--
    Elora footer (Figma "Elora Variants" footer 1:26977 / phone m 1:21125 bottom): #3A3A3A block —
    store logo, "We accept" payment badges (the store's gateways) at the start, link columns
    (store pages + catalog links) at the end; grey legal bar; dark copyright bar. Phones: centred
    logo, two-column links. The store's about line and social icons (Appearance, VEN-034e/f) sit under the logo.
--}}
@props(['columns' => [], 'legal' => [], 'name', 'copyright' => null])
@if (app(\App\Modules\Storefront\Services\StorefrontContext::class)->theme()->variant === 1)
    <x-sf::footer.elora-v1 :name="$name" :copyright="$copyright" />
@else
<footer class="elo-footer mt-auto text-white" aria-label="{{ __st('shell.footer.label') }}">
    <div class="mx-auto grid max-w-[1440px] gap-10 px-6 py-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:px-[152px]">
        <div class="grid content-start gap-6 max-lg:justify-items-center">
            <x-sf::logo tone="light" size="lg" />
            <x-sf::footer.social class="max-lg:justify-items-center max-lg:text-center" />
            <div class="grid gap-3 max-lg:justify-items-center">
                <p class="text-base font-medium">{{ __st('elora.footer.we_accept') }}</p>
                <x-sf::cart.pay-badges class="elo-footer-badges" />
            </div>
        </div>
        @if ($columns)
            <div class="grid grid-cols-2 gap-x-8 gap-y-8 sm:grid-cols-3 lg:justify-items-end">
                @foreach ($columns as $key => $links)
                    <div class="grid content-start gap-4">
                        <h2 class="text-xl font-medium max-lg:text-base">{{ __st('shell.footer.columns.'.$key) }}</h2>
                        <ul class="grid gap-3" role="list">
                            @foreach ($links as $link)<li><a href="{{ $link['url'] }}" class="text-sm text-white/90 transition hover:text-white hover:underline">{{ $link['title'] }}</a></li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @if ($legal)
        <nav class="bg-[#4d4d4d]" aria-label="{{ __st('shell.footer.legal') }}">
            <div class="mx-auto flex max-w-[1440px] flex-wrap justify-center gap-x-24 gap-y-2 px-6 py-3 text-sm max-lg:gap-x-10">
                @foreach ($legal as $link)<a href="{{ $link['url'] }}" class="hover:underline">{{ $link['title'] }}</a>@endforeach
            </div>
        </nav>
    @endif
    <div class="bg-[#262626] py-3 text-center text-sm max-lg:pb-24">
        <p>{{ $copyright ?? __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
    </div>
</footer>
@endif
{{-- One variant picker per Elora page, shared by every card's cart button (catalog/variant-picker.js). --}}
<x-sf::variant-picker theme="elora" />
