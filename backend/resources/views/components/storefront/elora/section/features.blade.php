{{--
    Elora trust strip. style="dark" (v1: #242424 bar, four icon + title items) or "marquee"
    (v2–v6 "Featured" 1:26572 / component 1:29865: coloured strip, icon + title + subtitle,
    infinite marquee — draggable, pauses on hover / touch / focus / off-screen, RTL,
    static with reduced motion). Texts are store static texts (elora.features.*).
--}}
@props(['style' => 'marquee', 'store' => ''])
@php
    $items = $style === 'dark'
        ? [['lock', 'privacy'], ['credit-card', 'payments'], ['check-circle', 'recovery'], ['truck', 'shipping']]
        : [['truck', 'shipping'], ['lifebuoy', 'support'], ['shield', 'secure'], ['box', 'money_back']];
@endphp
@if ($style === 'dark')
    <section class="elo-features-dark" data-reveal="fade" aria-label="{{ __st('elora.features.label') }}">
        <ul class="elo-home-wrap flex items-center justify-center gap-x-16 gap-y-3 max-lg:justify-start max-lg:gap-x-6 max-lg:overflow-x-auto max-lg:[scrollbar-width:none]" role="list">
            @foreach ($items as [$icon, $key])
                <li class="flex shrink-0 items-center gap-3 whitespace-nowrap"><x-ui::icon :name="$icon" :size="24" class="max-lg:size-4" />{{ __st('elora.features.'.$key.'.title') }}</li>
            @endforeach
        </ul>
    </section>
@else
    <section class="elo-features mo-marquee" data-marquee='{"speed":36}' aria-label="{{ __st('elora.features.label') }}">
        <div class="mo-marquee-lane">
            <ul class="flex shrink-0 items-center gap-14 pe-14 max-lg:gap-8 max-lg:pe-8" role="list" data-marquee-track>
                @foreach ($items as [$icon, $key])
                    <li class="flex shrink-0 items-center gap-6 max-lg:gap-2">
                        <x-ui::icon :name="$icon" :size="40" class="max-lg:size-5" />
                        <span class="grid">
                            <span class="text-xl font-semibold leading-[25px] max-lg:text-xs max-lg:font-normal">{{ __st('elora.features.'.$key.'.title') }}</span>
                            <span class="text-xl font-light leading-[25px] opacity-90 max-lg:hidden">{{ __st('elora.features.'.$key.'.body') }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
