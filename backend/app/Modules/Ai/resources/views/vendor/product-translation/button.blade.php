{{--
    VEN-012b "Translate with AI" header button of the own-product form (opens
    #product-ai-translate-modal, ai::vendor.product-translation.modal). Data (view
    composer): $product, $aiLanguages, $aiCanManage.
--}}
@php
    $aiCanManage ??= false;
    $aiLanguages ??= [];
@endphp
@if ($aiCanManage && count($aiLanguages) > 1)
    <button type="button" class="vnd-cta vnd-cta-outline flex-1 sm:flex-none sm:px-5" data-overlay-open="product-ai-translate-modal"><x-ui::icon name="sparkles" :size="20" />{{ __('ai::product_translation.translate') }}</button>
@endif
