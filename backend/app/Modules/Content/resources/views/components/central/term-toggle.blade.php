{{--
    Billing term toggle (Figma Component 1 12:41089): #FFF2EF pill, 146×48 segments,
    active = orange fill + white Md-20. Drives the parent Alpine `term` and keeps ?term
    in the URL (history.replaceState) without reloading.
--}}
<div {{ $attributes->class(['site-toggle relative inline-flex h-12 items-center gap-4 rounded-[72px] bg-site-tint']) }} role="radiogroup" aria-label="{{ __('content::site.pricing.term_label') }}" data-term-toggle>
    <span class="site-toggle__thumb absolute inset-y-0 start-0 w-[146px] rounded-[56px] bg-primary max-sm:w-[120px]" :class="term === 'yearly' && 'is-end'" aria-hidden="true"></span>
    @foreach (['monthly', 'yearly'] as $option)
        <button type="button" role="radio" class="relative z-10 flex h-12 w-[146px] items-center justify-center rounded-[56px] text-lg font-medium transition-colors duration-300 max-sm:w-[120px] sm:text-xl"
            :class="term === '{{ $option }}' ? 'text-white' : 'text-primary'" :aria-checked="(term === '{{ $option }}').toString()" @click="setTerm('{{ $option }}')">
            {{ __('content::site.pricing.'.$option) }}
        </button>
    @endforeach
</div>
