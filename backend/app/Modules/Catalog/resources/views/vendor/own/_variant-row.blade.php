{{-- One own-product variant row (VEN-016c); index "__INDEX__" inside the JS template. --}}
<fieldset class="grid gap-3 rounded-card border border-line p-4" data-variant-row>
    <legend class="sr-only">{{ __('catalog::vendor.own.variant') }}</legend>
    @if ($variant)
        <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}">
    @endif
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($locales as $code => $language)
            <x-ui::input :name="'variants['.$index.'][title]['.$code.']'" :id="'variant-'.$index.'-title-'.$code" :label="__('catalog::vendor.own.fields.variant_title', ['locale' => strtoupper($code)])" :value="$variant?->getTranslation('title', $code, false)" :required="$code === $fallback" :dir="$language['dir'] ?? 'ltr'" maxlength="120" />
        @endforeach
    </div>
    <div class="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]">
        <x-ui::input :name="'variants['.$index.'][sku]'" :id="'variant-'.$index.'-sku'" :label="__('catalog::vendor.own.fields.sku')" :value="$variant?->sku" dir="ltr" />
        <x-ui::input :name="'variants['.$index.'][price]'" :id="'variant-'.$index.'-price'" type="number" step="0.01" min="0" :label="__('catalog::vendor.own.fields.base_price')" :value="$variant?->price?->toDecimal()" required dir="ltr" inputmode="decimal" />
        <x-ui::input :name="'variants['.$index.'][sale_price]'" :id="'variant-'.$index.'-sale'" type="number" step="0.01" min="0" :label="__('catalog::vendor.own.fields.sale_price')" :value="$variant?->sale_price?->toDecimal()" dir="ltr" inputmode="decimal" />
        <x-ui::input :name="'variants['.$index.'][stock]'" :id="'variant-'.$index.'-stock'" type="number" min="0" :label="__('catalog::vendor.edit.stock')" :value="$variant?->stock ?? 0" required dir="ltr" inputmode="numeric" />
        <x-ui::checkbox :name="'variants['.$index.'][is_active]'" :id="'variant-'.$index.'-active'" :label="__('catalog::vendor.products.active')" :checked="$variant?->is_active ?? true" unchecked-value="0" />
        <x-ui::icon-button icon="trash" :label="__('catalog::vendor.own.remove_variant')" data-variant-remove />
    </div>
</fieldset>
