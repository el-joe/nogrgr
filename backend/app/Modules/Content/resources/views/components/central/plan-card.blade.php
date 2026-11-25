{{--
    Plan card (Figma Plans 12:41596 — regular 308 wide, 1px orange border, radius 40;
    featured 364 wide, orange fill, white text, shadow 0 8 10 rgba(255,82,44,.16)).
    Both term prices are rendered; the parent's Alpine `term` ('monthly'|'yearly') picks
    one and rewrites the CTA links. Lines: highlights, every PlanLimit (null = unlimited),
    every PlanFeature (check / cross); the first 6 show, "Show More +" expands the rest.
--}}
@props(['plan', 'term' => 'monthly', 'featured' => false])
@use('App\Modules\Billing\Enums\BillingTerm')
@use('App\Modules\Billing\Enums\PlanFeature')
@use('App\Modules\Billing\Enums\PlanLimit')
@php
    $priceText = function (BillingTerm $billingTerm) use ($plan): string {
        $formatted = $plan->priceFor($billingTerm)->format(\App\Shared\Support\Locale::numberLocale());

        return (string) preg_replace('/[.,٫]0{2}(?!\d)/u', '', $formatted);
    };
    $lines = collect($plan->highlightLines())->map(fn (string $text): array => ['text' => $text, 'ok' => true]);
    foreach (PlanLimit::cases() as $limit) {
        $value = $plan->limit($limit);
        if ($value === 0) {
            $lines->push(['text' => $limit->label(), 'ok' => false]);
            continue;
        }
        $lines->push(['text' => __('content::site.pricing.limit', ['label' => $limit->label(), 'value' => $value === null ? __('content::site.common.unlimited') : \Illuminate\Support\Number::format($value, locale: \App\Shared\Support\Locale::numberLocale())]), 'ok' => true]);
    }
    foreach (PlanFeature::cases() as $feature) {
        $lines->push(['text' => $feature->label(), 'ok' => $plan->hasFeature($feature)]);
    }
    $visible = 6;
    $hasRegister = \Illuminate\Support\Facades\Route::has('central.register');
    $urls = $hasRegister ? [
        'monthly' => route('central.register', ['plan' => $plan->id, 'term' => 'monthly']),
        'yearly' => route('central.register', ['plan' => $plan->id, 'term' => 'yearly']),
    ] : ['monthly' => null, 'yearly' => null];
    $inverse = $featured;
@endphp
<article {{ $attributes->class([
    'site-plan relative flex flex-col overflow-hidden rounded-[40px]',
    'site-plan--featured bg-primary text-white shadow-[0_8px_10px_rgba(255,82,44,0.16)] px-10 py-11 lg:w-[364px]' => $featured,
    'border border-primary bg-white px-7 py-10 lg:w-[308px]' => ! $featured,
]) }} x-data="{ more: false }" aria-labelledby="plan-{{ $plan->id }}-name">
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2.5">
                <span @class(['grid size-12 shrink-0 place-items-center rounded-[40px]', 'bg-white' => $inverse, 'bg-site-tint' => ! $inverse])>
                    <x-ui::icon :name="$plan->icon ?: 'zap'" :size="28" class="text-primary" />
                </span>
                <h3 id="plan-{{ $plan->id }}-name" @class(['text-xl font-medium', 'text-white' => $inverse, 'text-site-ink' => ! $inverse])>{{ $plan->name }}</h3>
            </div>
            @foreach (['monthly' => BillingTerm::Monthly, 'yearly' => BillingTerm::Yearly] as $key => $billingTerm)
                <p class="flex flex-wrap items-baseline gap-x-3 tracking-[0.5px]" x-show="term === '{{ $key }}'" @if ($term !== $key) x-cloak @endif>
                    <span @class(['text-[40px] leading-tight font-extrabold', 'text-site-ink' => ! $inverse]) dir="ltr">{{ $priceText($billingTerm) }}</span>
                    <span @class(['text-xl', 'text-site-gray' => ! $inverse, 'text-white' => $inverse])><span class="text-base">/</span> {{ __('content::site.pricing.'.($key === 'yearly' ? 'per_year' : 'per_month')) }}</span>
                </p>
            @endforeach
            <p @class(['text-base', 'text-xl text-white' => $inverse, 'text-site-ink' => ! $inverse])>{{ __('content::site.pricing.included') }}</p>
        </div>
        <span @class(['block h-px w-[234px] max-w-full', 'bg-white' => $inverse, 'bg-primary' => ! $inverse])></span>
    </div>

    <ul class="mt-6 flex flex-col gap-4" id="plan-{{ $plan->id }}-lines">
        @foreach ($lines as $line)
            <li class="flex items-start gap-2 {{ $inverse ? 'gap-4' : '' }}" @if ($loop->index >= $visible) x-show="more" x-cloak x-transition.opacity.duration.250ms @endif>
                @if ($line['ok'])
                    <span @class(['grid size-6 shrink-0 place-items-center rounded-full', 'bg-white text-primary' => $inverse, 'bg-primary text-white' => ! $inverse])>
                        <x-ui::icon name="check" :size="14" />
                    </span>
                @else
                    <span @class(['grid size-6 shrink-0 place-items-center rounded-full border', 'border-white text-white' => $inverse, 'border-primary text-primary' => ! $inverse])>
                        <x-ui::icon name="x" :size="12" />
                    </span>
                @endif
                <span @class(['leading-[25px] tracking-[0.5px]', 'text-base text-white' => $inverse, 'text-sm text-site-gray' => ! $inverse])>
                    <span class="sr-only">{{ __('content::site.pricing.'.($line['ok'] ? 'included_yes' : 'included_no')) }}:</span>
                    {{ $line['text'] }}
                </span>
            </li>
        @endforeach
    </ul>
    @if ($lines->count() > $visible)
        <button type="button" class="mt-4 self-start text-sm leading-[25px] font-medium tracking-[0.5px] {{ $inverse ? 'text-white' : 'text-site-gray' }} hover:underline" @click="more = !more" :aria-expanded="more.toString()" aria-controls="plan-{{ $plan->id }}-lines">
            <span x-show="!more">{{ __('content::site.pricing.show_more') }}</span>
            <span x-show="more" x-cloak>{{ __('content::site.pricing.show_less') }}</span>
        </button>
    @endif

    @if ($hasRegister)
        <div class="mt-auto flex gap-2.5 pt-6">
            @if ($plan->trial_days > 0)
                <a href="{{ $urls[$term] ?? $urls['monthly'] }}" :href="term === 'yearly' ? @js($urls['yearly']) : @js($urls['monthly'])"
                    @class(['site-btn flex h-12 w-[99px] shrink-0 items-center justify-center rounded-md border-[1.2px] text-base', 'border-white text-white hover:bg-white/10' => $inverse, 'border-primary text-primary hover:bg-site-tint' => ! $inverse])
                    title="{{ __('content::site.pricing.trial_days', ['days' => $plan->trial_days]) }}">{{ __('content::site.pricing.trial') }}</a>
            @endif
            <a href="{{ $urls[$term] ?? $urls['monthly'] }}" :href="term === 'yearly' ? @js($urls['yearly']) : @js($urls['monthly'])"
                @class(['site-btn flex h-12 flex-1 items-center justify-center rounded-md text-base', 'bg-white text-primary hover:bg-white/90' => $inverse, 'bg-primary text-white hover:bg-primary-hover' => ! $inverse])>{{ __('content::site.pricing.purchase') }}</a>
        </div>
    @endif
</article>
