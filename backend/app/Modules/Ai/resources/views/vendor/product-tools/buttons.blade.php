{{--
    Header buttons of a product page opening the OpenAI product tools: VEN-010b AI price
    finder (#product-ai-price-modal) and VEN-010c AI social posts (#product-ai-social-modal).
    Data (view composer): $product, $aiTools (null = hidden: OpenAI not configured or no
    manage permission). $style: 'button' (x-ui::button, imported product page) or 'cta'
    (vnd-cta, own-product form).
--}}
@php($aiTools ??= null)
@php($style ??= 'button')
@if ($aiTools !== null)
    @if ($style === 'cta')
        <button type="button" class="vnd-cta vnd-cta-outline flex-1 sm:flex-none sm:px-5" data-overlay-open="product-ai-price-modal"><x-ui::icon name="tag" :size="20" />{{ __('ai::tools.price_finder.open') }}</button>
        <button type="button" class="vnd-cta vnd-cta-outline flex-1 sm:flex-none sm:px-5" data-overlay-open="product-ai-social-modal"><x-ui::icon name="sparkles" :size="20" />{{ __('ai::tools.social.open') }}</button>
    @else
        <x-ui::button variant="secondary" size="lg" icon="tag" class="flex-1 sm:flex-none" data-overlay-open="product-ai-price-modal">{{ __('ai::tools.price_finder.open') }}</x-ui::button>
        <x-ui::button variant="secondary" size="lg" icon="sparkles" class="flex-1 sm:flex-none" data-overlay-open="product-ai-social-modal">{{ __('ai::tools.social.open') }}</x-ui::button>
    @endif
@endif
