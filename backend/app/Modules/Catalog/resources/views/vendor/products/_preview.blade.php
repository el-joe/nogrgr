{{--
    New product preview (VEN-011a, Vendor Panel (new screens) 83:104358): how it looks on the
    storefront — image, price with the store's default markup (Commerce\Pricing via the
    store price table after adding), profit and countries.
    Data: $product (central Product), $countries, $imported, $defaultPercentage, $unit (UnitPrice).
--}}
@php
    $image = $product->media->first()?->url();
@endphp
<div class="grid gap-4" data-modal-title="{{ __('catalog::vendor.new.preview_named', ['name' => $product->name]) }}" data-preview-for="{{ $product->id }}" @if ($imported) data-imported @endif>
    <div class="aspect-[512/220] overflow-hidden rounded-card bg-subtle">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $product->name }}" class="size-full object-contain bg-media">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="image" :size="40" /></div>
        @endif
    </div>
    @if ($product->summary)
        <p class="text-sm leading-6 text-ink-secondary">{{ $product->summary }}</p>
    @endif
    <dl class="grid gap-2.5 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.new.price') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $unit->price->format() }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.edit.profit') }}</dt><dd class="font-semibold text-ink" dir="ltr">{{ $unit->profitAmount->format() }} ({{ rtrim(rtrim(number_format($defaultPercentage, 2), '0'), '.') ?: '0' }}%)</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.new.countries') }}</dt><dd class="text-end font-semibold text-ink">{{ $countries->isEmpty() ? '—' : $countries->pluck('iso2')->implode(', ') }}</dd></div>
        @if ($product->variants->isNotEmpty())
            <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor.new.variants') }}</dt><dd class="font-semibold text-ink">{{ number_format($product->variants->count()) }}</dd></div>
        @endif
    </dl>
    <p class="text-xs text-ink-muted">{{ __('catalog::vendor.new.preview_hint') }}</p>
    @if ($imported)
        <p class="rounded-control bg-success-bg p-3 text-sm text-success">{{ __('catalog::vendor.new.already') }}</p>
    @endif
</div>
