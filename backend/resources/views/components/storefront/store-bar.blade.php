{{--
    Store bar above every theme header (Vendor › Appearance, VEN-034c/d): the store's promo
    message (own background from the appearance stylesheet, --sf-promo-bg / --sf-promo-ink)
    and its header links. Rendered only when the store set either, so the theme's Figma
    header is unchanged by default. Phones: the links scroll sideways under the message.
--}}
@php
    $appearance = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class);
    $promo = $appearance->promoBar();
    $links = $appearance->headerLinks();
@endphp
@if ($promo || $links)
    <div class="sf-store-bar relative z-30 bg-[var(--sf-promo-bg,#0b0b0b)] text-[var(--sf-promo-ink,#ffffff)]" data-store-bar>
        <div class="mx-auto flex max-w-[1440px] flex-col items-center gap-x-6 gap-y-1 px-4 py-2 text-[13px] leading-5 sm:flex-row sm:justify-between lg:px-8">
            @if ($promo)
                <p class="min-w-0 text-center sm:flex-1 sm:text-start">
                    @if ($promo['url'])
                        <a href="{{ $promo['url'] }}" class="underline-offset-2 transition-opacity hover:underline hover:opacity-90" @if ($promo['external']) target="_blank" rel="noopener" @endif>{{ $promo['text'] }}</a>
                    @else
                        {{ $promo['text'] }}
                    @endif
                </p>
            @endif
            @if ($links)
                <nav @class(['-mx-4 max-w-full overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0', 'sm:ms-auto' => ! $promo]) aria-label="{{ __st('appearance.store_links') }}">
                    <ul class="flex items-center gap-5 whitespace-nowrap" role="list">
                        @foreach ($links as $link)
                            <li><a href="{{ $link['url'] }}" class="opacity-90 underline-offset-2 transition-opacity hover:underline hover:opacity-100" @if ($link['external']) target="_blank" rel="noopener" @endif>{{ $link['title'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
@endif
