{{--
    "Why choose {store}?" trust strip (Ecommet 1:1006): green header with promises + security
    reminder. Desktop only in Figma. "Delivery guarantee >" opens the store's shipping page and
    the reminder's "View >" its privacy page (FAQ / terms as fallbacks); without a published
    page the item stays plain text, so there is never a dead link.
--}}
@props(['store'])
@php
    $pages = app(\App\Modules\Storefront\Services\Pages\StorePageReader::class);
    $pageUrl = static function (array $slugs) use ($pages): ?string {
        foreach ($slugs as $slug) {
            if ($pages->find($slug) !== null) {
                return route('storefront.pages.show', $slug);
            }
        }

        return null;
    };
    $deliveryUrl = $pageUrl(['shipping-info']);
    $safetyUrl = $pageUrl(['privacy', 'faq', 'terms']);
@endphp
<section {{ $attributes->class(['eco-why']) }} aria-labelledby="sf-why-title" data-reveal="fade-up">
    <div class="eco-why-head">
        <h2 id="sf-why-title" class="inline-flex items-center gap-1.5 text-lg font-medium"><x-ui::icon name="security-time" :size="20" />{{ __st('catalog.home.why_title', ['store' => $store]) }}</h2>
        <ul class="flex flex-wrap items-center gap-[18px] text-sm leading-[25px] tracking-[0.5px]" role="list">
            <li class="inline-flex items-center gap-1.5 border-e border-eco-semi px-2"><x-ui::icon name="lock" :size="20" />{{ __st('catalog.home.why_privacy') }}</li>
            <li class="inline-flex items-center gap-1.5 border-e border-eco-semi pe-2"><x-ui::icon name="credit-card-2" :size="20" />{{ __st('catalog.home.why_payments') }}</li>
            <li>
                @if ($deliveryUrl)
                    <a href="{{ $deliveryUrl }}" class="group inline-flex items-center gap-1.5 hover:underline"><x-ui::icon name="truck-tick" :size="20" />{{ __st('catalog.home.why_delivery') }}<x-ui::icon name="chevron-right" :size="16" class="transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" /></a>
                @else
                    <span class="inline-flex items-center gap-1.5"><x-ui::icon name="truck-tick" :size="20" />{{ __st('catalog.home.why_delivery') }}</span>
                @endif
            </li>
        </ul>
    </div>
    <div class="flex min-h-10 items-center justify-between gap-4 px-[15px] py-2 text-sm leading-[25px] tracking-[0.5px] text-eco-green">
        <p class="inline-flex items-center gap-1.5"><x-ui::icon name="notification" :size="24" class="shrink-0" />{{ __st('catalog.home.security_reminder', ['store' => $store]) }}</p>
        @if ($safetyUrl)
            <a href="{{ $safetyUrl }}" class="group inline-flex shrink-0 items-center gap-1 hover:underline">{{ __st('catalog.home.why_view') }}<x-ui::icon name="chevron-right" :size="16" class="transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" /></a>
        @endif
    </div>
</section>
