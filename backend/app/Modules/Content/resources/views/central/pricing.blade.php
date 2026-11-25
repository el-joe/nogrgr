{{--
    Pricing (Figma 12:42011, mobile 12:43222): term toggle + plan cards (featured middle).
    Data: $seo, $plans, $term ('monthly'|'yearly'). The toggle switches client-side and
    keeps ?term in the URL and in every CTA.
--}}
@php
    $plans = collect($plans ?? []);
    $term = in_array($term ?? 'monthly', ['monthly', 'yearly'], true) ? $term : 'monthly';
    $featuredIndex = $plans->count() >= 3 ? intdiv($plans->count() - 1, 2) : null;
@endphp
<x-central::layout :seo="$seo" page="site-pricing">
    <section class="relative px-0 pt-6 pb-16 lg:pt-[15px] lg:pb-[72px]" aria-labelledby="pricing-title"
        x-data="{ term: @js($term), setTerm(value) { this.term = value; const url = new URL(window.location.href); url.searchParams.set('term', value); window.history.replaceState(null, '', url); } }">
        <span class="site-glow site-glow--page" aria-hidden="true"></span>
        <h1 id="pricing-title" class="mb-4 px-4 text-center text-2xl font-medium text-site-ink lg:sr-only">{{ __('content::site.pricing.heading') }}</h1>
        @if ($plans->isNotEmpty())
            <div class="flex justify-center px-4" data-reveal="fade-up"><x-central::term-toggle /></div>
            <div class="swiper site-rail site-plans mt-8 lg:mt-[56px]" data-rail data-rail-until="1024" data-rail-centered data-rail-initial="{{ $featuredIndex ?? 0 }}">
                <ul class="swiper-wrapper items-center lg:justify-center lg:gap-6">
                    @foreach ($plans as $plan)
                        <li class="swiper-slide !w-[216px] sm:!w-[308px] lg:!w-auto" data-reveal="fade-up" data-reveal-delay="{{ $loop->index * 90 }}">
                            <x-central::plan-card :plan="$plan" :term="$term" :featured="$loop->index === $featuredIndex" class="max-sm:[zoom:0.7]" />
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <x-central::empty icon="credit-card" :title="__('content::site.pricing.empty_title')" :text="__('content::site.pricing.empty_text')"
                :action-url="Route::has('central.contact') ? route('central.contact') : null" :action-label="__('content::site.common.contact_us')" />
        @endif
    </section>
</x-central::layout>
