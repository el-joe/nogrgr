{{--
    Opens the AI Advertising Studio (VEN-010c) for a product (product pages) or with the
    product picker. $style: 'button' (x-ui::button) | 'cta' (vnd-cta). Hidden without
    marketing.ads.manage or an OpenAI key. Data (view composer): $adStudio, $product?.
--}}
@php($adStudio ??= null)
@php($style ??= 'button')
@if ($adStudio !== null && $adStudio['canCreate'])
    @php($card = $adStudio['product'])
    @if ($style === 'cta')
        <button type="button" class="vnd-cta vnd-cta-outline flex-1 sm:flex-none sm:px-5" data-ad-studio-open @if ($card) data-product-id="{{ $card['id'] }}" data-product-name="{{ $card['name'] }}" data-product-image="{{ $card['image'] }}" @endif><x-ui::icon name="ad-video" :size="20" />{{ __('ai::ad_studio.create') }}</button>
    @else
        <x-ui::button variant="secondary" size="lg" icon="ad-video" class="flex-1 sm:flex-none" data-ad-studio-open :data-product-id="$card['id'] ?? null" :data-product-name="$card['name'] ?? null" :data-product-image="$card['image'] ?? null">{{ __('ai::ad_studio.create') }}</x-ui::button>
    @endif
@endif
