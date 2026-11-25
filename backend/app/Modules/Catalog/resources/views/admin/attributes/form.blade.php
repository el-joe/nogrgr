{{--
    Admin › Catalog › Attributes › Create / Edit (ADM-015a, Figma Admin Panel 65:26338):
    translated name, display type, active switch, the options repeater (one name per
    language, swatch for colour attributes, active switch, drag to reorder) and usage.
--}}
@php
    $isNew = ! $attribute->exists;
    $title = $isNew ? __('catalog::attributes.create_title') : __('catalog::attributes.edit_title');
    $swatch = ($attribute->display_type ?? \App\Modules\Catalog\Enums\AttributeDisplayType::Text)->usesSwatch();
    $options = $isNew ? collect([null]) : $attribute->options;
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-catalog">
    <form
        id="attribute-form"
        method="POST"
        action="{{ $isNew ? route('admin.catalog.attributes.store') : route('admin.catalog.attributes.update', $attribute) }}"
        data-ajax
        data-unsaved-form
        data-attribute-form
        class="grid gap-6"
        novalidate
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::attributes.title') => route('admin.catalog.attributes.index'), ($isNew ? __('catalog::categories.new') : $attribute->name) => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.catalog.attributes.index')">{{ __('catalog::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('catalog::common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid gap-6">
                <x-ui::card :title="__('catalog::attributes.sections.attribute')">
                    <div class="grid gap-5">
                        <x-ui::locale-tabs id="attribute-i18n" :locales="$locales">
                            @foreach ($locales as $code => $language)
                                <x-ui::locale-pane parent="attribute-i18n" :locale="$code">
                                    <x-ui::input :name="'name['.$code.']'" :label="__('catalog::attributes.fields.name')" :value="$isNew ? null : $attribute->getTranslation('name', $code, false)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="80" :dir="$language['dir']" />
                                </x-ui::locale-pane>
                            @endforeach
                        </x-ui::locale-tabs>
                        <div class="grid gap-5 sm:grid-cols-2 sm:items-end">
                            <x-ui::select name="display_type" :label="__('catalog::attributes.fields.display_type')" :options="\App\Modules\Catalog\Enums\AttributeDisplayType::options()" :selected="$attribute->display_type?->value ?? 'text'" :search="false" required data-display-type />
                            <x-ui::switch name="status" value="active" unchecked-value="inactive" class="pb-3" :label="__('catalog::attributes.fields.active')" :checked="$isNew || $attribute->status === \App\Modules\Catalog\Enums\RecordStatus::Active" />
                        </div>
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('catalog::attributes.sections.options')" :subtitle="__('catalog::attributes.sections.options_hint')">
                    <x-slot:actions>
                        <x-ui::button variant="secondary" size="sm" icon="plus" data-option-add>{{ __('catalog::attributes.add_option') }}</x-ui::button>
                    </x-slot:actions>
                    <ol data-option-list data-sortable-list class="grid gap-3" data-next-index="{{ $options->count() }}">
                        @foreach ($options as $index => $option)
                            @include('catalog::admin.attributes._option-row', ['index' => $index, 'option' => $option, 'locales' => $locales, 'locked' => $locked, 'swatch' => $swatch])
                        @endforeach
                    </ol>
                    <p data-option-empty @if ($options->isNotEmpty()) hidden @endif class="py-6 text-center text-sm text-ink-secondary">{{ __('catalog::attributes.options_empty') }}</p>
                    <template data-option-template>
                        @include('catalog::admin.attributes._option-row', ['index' => '__INDEX__', 'option' => null, 'locales' => $locales, 'locked' => [], 'swatch' => $swatch])
                    </template>
                </x-ui::card>
            </div>

            @if ($usage !== null)
                <x-ui::card :title="__('catalog::attributes.sections.usage')">
                    <dl class="grid gap-3 text-[13px]">
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::attributes.usage.products') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format($usage['products']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::attributes.usage.variants') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ number_format($usage['variants']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::attributes.usage.last_edited') }}</dt><dd class="font-medium text-ink">{{ $attribute->updated_at?->diffForHumans() }}</dd></div>
                    </dl>
                    <p class="mt-4 flex gap-2 rounded-control bg-warning-bg p-3 text-xs text-warning" role="note">
                        <x-ui::icon name="alert-triangle" :size="16" class="shrink-0" />
                        {{ __('catalog::attributes.usage.note') }}
                    </p>
                </x-ui::card>
            @endif
        </div>

        <div data-unsaved-bar hidden class="sticky bottom-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface px-4 py-3 shadow-overlay sm:-mx-6 sm:px-6 xl:-mx-8 xl:px-8">
            <p class="text-sm font-medium text-ink">{{ __('catalog::common.unsaved') }}</p>
            <div class="flex gap-3">
                <x-ui::button variant="secondary" data-unsaved-discard>{{ __('catalog::common.discard') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('catalog::common.save_changes') }}</x-ui::button>
            </div>
        </div>
    </form>
</x-layouts.panel>
