{{--
    Label create / edit (ADM-016a, Figma Admin Panel 65:29682), loaded into #label-modal:
    text per language with known {placeholders}, icon, sort, countries, active and a live
    preview using sample values.
--}}
@php
    $editing = $label->exists;
@endphp
<form id="label-form" method="POST" action="{{ $editing ? route('admin.catalog.labels.update', $label) : route('admin.catalog.labels.store') }}" data-ajax novalidate class="grid gap-5"
    data-modal-title="{{ $editing ? __('catalog::labels.edit_title') : __('catalog::labels.create_title') }}" data-submit-label="{{ __('catalog::common.save') }}" data-label-form>
    @csrf
    @if ($editing)
        @method('PUT')
    @endif
    <script type="application/json" data-label-samples>@json($samples)</script>
    <x-ui::locale-tabs id="label-i18n" :locales="$locales">
        @foreach ($locales as $code => $language)
            <x-ui::locale-pane parent="label-i18n" :locale="$code">
                <x-ui::input :name="'text['.$code.']'" :label="__('catalog::labels.fields.text')" :value="$editing ? $label->getTranslation('text', $code, false) : null" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="120" :dir="$language['dir']" data-label-text="{{ $code }}"
                    :helper="__('catalog::labels.fields.text_help', ['placeholders' => implode(', ', \App\Modules\Catalog\Enums\LabelPlaceholder::tokens())])" />
            </x-ui::locale-pane>
        @endforeach
    </x-ui::locale-tabs>
    <div class="grid gap-5 sm:grid-cols-2">
        <x-ui::icon-picker name="icon" :label="__('catalog::labels.fields.icon')" :selected="$label->icon" :placeholder="__('catalog::labels.fields.icon_none')" />
        <x-ui::input name="sort_order" type="number" min="0" max="65535" :label="__('catalog::labels.fields.sort_order')" :value="$editing ? $label->sort_order : null" dir="ltr" />
    </div>
    <x-ui::select name="country_ids" multiple :label="__('catalog::labels.fields.countries')" :options="$countries" :selected="$label->country_ids ?? []" :placeholder="__('catalog::labels.fields.countries_all')" />
    <x-ui::switch name="is_active" :label="__('catalog::labels.fields.active')" :checked="(bool) $label->is_active" />
    <p data-label-preview data-template="{{ __('catalog::labels.live_preview', ['text' => ':text']) }}" class="flex items-center gap-2 rounded-control bg-success-bg px-3 py-2.5 text-[13px] text-success" role="status" aria-live="polite">
        <x-ui::icon name="check-circle" :size="16" class="shrink-0" />
        <span data-label-preview-text>{{ __('catalog::labels.live_preview', ['text' => $editing ? strtr((string) $label->text, $samples) : '']) }}</span>
    </p>
</form>
