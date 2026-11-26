{{--
    Souqify trust strip. style="icons" (v1 1:73210: amber bar, coloured icon + title, four
    items) or "marquee" (v2–v6: variant-coloured strip, icon + title + subtitle). Both run as
    an infinite marquee (draggable, pauses on hover / touch / focus / off-screen, RTL, static
    with reduced motion). Texts are store static texts (souqify.features.*).
--}}
@props(['style' => 'marquee'])
@php
    $items = $style === 'icons'
        ? [['lock', 'privacy'], ['credit-card', 'payments'], ['check-circle', 'recovery'], ['truck', 'shipping'], ['gift', 'offers']]
        : [['truck', 'shipping'], ['lifebuoy', 'support'], ['shield', 'secure'], ['box', 'money_back']];
@endphp
<section @class(['sq-features mo-marquee', 'sq-features-icons' => $style === 'icons']) data-marquee='{"speed":34}' aria-label="{{ __st('souqify.features.label') }}">
    <div class="mo-marquee-lane">
        <ul class="flex shrink-0 items-center gap-[120px] pe-[120px] max-lg:gap-8 max-lg:pe-8" role="list" data-marquee-track>
            @foreach ($items as [$icon, $key])
                <li class="flex shrink-0 items-center gap-4 max-lg:gap-2">
                    <span class="sq-features-icon"><x-ui::icon :name="$icon" :size="$style === 'icons' ? 26 : 32" class="max-lg:size-4" /></span>
                    <span class="grid">
                        <span class="sq-features-title">{{ __st('souqify.features.'.$key.'.title') }}</span>
                        @if ($style !== 'icons')<span class="sq-features-body">{{ __st('souqify.features.'.$key.'.body') }}</span>@endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
</section>
