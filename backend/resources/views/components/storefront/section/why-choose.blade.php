{{-- "Why choose {store}?" trust strip (Ecommet 1:1006): green header with promises + security reminder. Desktop only in Figma. --}}
@props(['store'])
<section {{ $attributes->class(['eco-why']) }} aria-labelledby="sf-why-title" data-reveal="fade-up">
    <div class="eco-why-head">
        <h2 id="sf-why-title" class="inline-flex items-center gap-1.5 text-lg font-medium"><x-ui::icon name="security-time" :size="20" />{{ __st('catalog.home.why_title', ['store' => $store]) }}</h2>
        <ul class="flex flex-wrap items-center gap-[18px] text-sm leading-[25px] tracking-[0.5px]" role="list">
            <li class="inline-flex items-center gap-1.5 border-e border-eco-semi pe-2"><x-ui::icon name="lock" :size="20" />{{ __st('catalog.home.why_privacy') }}</li>
            <li class="inline-flex items-center gap-1.5 border-e border-eco-semi pe-2"><x-ui::icon name="credit-card-2" :size="20" />{{ __st('catalog.home.why_payments') }}</li>
            <li class="inline-flex items-center gap-1.5"><x-ui::icon name="truck-tick" :size="20" />{{ __st('catalog.home.why_delivery') }}</li>
        </ul>
    </div>
    <div class="flex min-h-10 items-center justify-between gap-4 px-[15px] py-2 text-sm leading-[25px] tracking-[0.5px] text-eco-green">
        <p class="inline-flex items-center gap-1.5"><x-ui::icon name="notification" :size="24" class="shrink-0" />{{ __st('catalog.home.security_reminder', ['store' => $store]) }}</p>
    </div>
</section>
