{{--
    VEN-012b Content & translations tab of the product edit page (Figma 83:101628): the
    product + variant texts per store language (store translation or catalogue text) and
    "Translate with AI" (modal ai::vendor.product-translation.modal, outside the edit form).
    Data (view composer): $product (StoreProduct), $aiLanguages (code => name), $aiContent
    (ProductAiTranslationService::content()), $aiCanManage.
--}}
@php
    $aiLanguages ??= [];
    $aiContent ??= [];
    $aiCanManage ??= false;
    $aiFieldLabel = fn (string $field): string => __('ai::product_translation.fields.'.$field);
    $aiItemLabel = fn (string $key): string => $key === 'product' ? __('ai::product_translation.product') : __('ai::product_translation.variant', ['id' => substr($key, 8)]);
    $aiFirst = array_key_first($aiLanguages);
@endphp
<section class="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card" data-ai-content>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="grid gap-1">
            <h2 class="text-base font-semibold text-ink">{{ __('ai::product_translation.title') }}</h2>
            <p class="text-sm text-ink-secondary">{{ __('ai::product_translation.subtitle') }}</p>
        </div>
        @if ($aiCanManage && count($aiLanguages) > 1)
            <x-ui::button type="button" size="sm" icon="sparkles" data-overlay-open="product-ai-translate-modal">{{ __('ai::product_translation.translate') }}</x-ui::button>
        @endif
    </div>

    @if ($aiContent === [])
        <x-ui::empty-state icon="languages" :title="__('ai::product_translation.no_languages')" />
    @else
        <x-ui::locale-tabs id="product-content-i18n" :locales="array_map(fn (string $name): array => ['name' => $name], $aiLanguages)" :active="$aiFirst">
            @foreach ($aiLanguages as $code => $language)
                <x-ui::locale-pane parent="product-content-i18n" :locale="$code" :active="$aiFirst">
                    <div class="grid gap-4 pt-4">
                        @foreach ($aiContent[$code] ?? [] as $key => $fields)
                            @if ($key !== 'product')
                                <h3 class="pt-2 text-sm font-semibold text-ink">{{ $aiItemLabel($key) }}</h3>
                            @endif
                            @foreach ($fields as $field => $cell)
                                <div class="grid gap-1.5">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-sm font-medium text-ink">{{ $aiFieldLabel($field) }}</span>
                                        @if ($cell['value'] !== '')
                                            <x-ui::badge :tone="$cell['overridden'] ? 'brand' : 'neutral'">{{ $cell['overridden'] ? __('ai::product_translation.store_text') : __('ai::product_translation.central_text') }}</x-ui::badge>
                                        @endif
                                    </div>
                                    <div @class([
                                        'rounded-control border border-line bg-subtle px-3 py-2.5 text-sm text-ink break-words',
                                        'min-h-28 whitespace-pre-line' => in_array($field, ['description', 'meta_description', 'summary'], true),
                                    ])>
                                        @if ($cell['value'] === '')
                                            <span class="text-ink-muted">{{ __('ai::product_translation.empty_value') }}</span>
                                        @else
                                            {{ strip_tags($cell['value']) }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </x-ui::locale-pane>
            @endforeach
        </x-ui::locale-tabs>
        @unless ($product->isOwn())
            <p class="text-xs text-ink-muted">{{ __('ai::product_translation.locked_hint') }}</p>
        @endunless
    @endif
</section>
