{{--
    VEN-012b "Translate with AI" modal: pick store languages → Translate (preview, nothing
    saved) → review / edit each text → Save translations; Regenerate asks again. Every
    field AI may write is rendered here hidden; panels/vendor/product-ai-translate.js shows
    and fills the ones the preview returns. Data: as the tab.
--}}
@php
    $aiCanManage ??= false;
    $aiLanguages ??= [];
    $aiContent ??= [];
@endphp
@if ($aiCanManage && count($aiLanguages) > 1)
    @php
        $aiFieldLabel = fn (string $field): string => __('ai::product_translation.fields.'.$field);
        $aiItemLabel = fn (string $key): string => $key === 'product' ? __('ai::product_translation.product') : __('ai::product_translation.variant', ['id' => substr($key, 8)]);
        $aiPrefix = $product->isOwn() ? 'catalog.own-products.ai-translate.' : 'catalog.products.ai-translate.';
    @endphp
    <x-ui::modal id="product-ai-translate-modal" :title="__('ai::product_translation.modal_title')" :subtitle="__('ai::product_translation.modal_intro')" size="lg">
        <div class="grid gap-5" data-ai-translate data-preview-url="{{ vendor_route($aiPrefix.'preview', ['product' => $product->id]) }}" data-apply-url="{{ vendor_route($aiPrefix.'apply', ['product' => $product->id]) }}">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium text-ink">{{ __('ai::product_translation.languages') }}</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-3">
                    @foreach ($aiLanguages as $code => $language)
                        @if (collect($aiContent[$code] ?? [])->flatten(1)->contains(fn ($cell) => $cell['translatable'] ?? false))
                            <x-ui::checkbox name="ai_locales[]" :value="$code" :label="$language" :checked="! in_array($code, ['ar', 'en'], true)" data-ai-locale />
                        @endif
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-4" hidden data-ai-preview aria-live="polite">
                <h3 class="text-sm font-semibold text-ink">{{ __('ai::product_translation.preview_title') }}</h3>
                @foreach ($aiLanguages as $code => $language)
                    <div hidden class="grid gap-3 rounded-card border border-line p-4" data-ai-preview-locale="{{ $code }}" @if ($code === 'ar') dir="rtl" @endif lang="{{ $code }}">
                        <p class="text-sm font-semibold text-ink-brand">{{ $language }}</p>
                        @foreach ($aiContent[$code] ?? [] as $key => $fields)
                            @foreach ($fields as $field => $cell)
                                @if ($cell['translatable'])
                                    <div hidden data-ai-preview-field="{{ $code }}|{{ $key }}|{{ $field }}">
                                        <x-ui::textarea :name="'translations['.$code.']['.$key.']['.$field.']'" :label="($key === 'product' ? '' : $aiItemLabel($key).' · ').$aiFieldLabel($field)" :rows="in_array($field, ['description', 'meta_description'], true) ? 5 : 2" maxlength="20000" />
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ai::product_translation.cancel') }}</x-ui::button>
            <x-ui::button variant="secondary" icon="refresh" hidden data-ai-regenerate>{{ __('ai::product_translation.regenerate') }}</x-ui::button>
            <x-ui::button icon="sparkles" data-ai-generate>{{ __('ai::product_translation.generate') }}</x-ui::button>
            <x-ui::button icon="check" hidden data-ai-apply>{{ __('ai::product_translation.apply') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
