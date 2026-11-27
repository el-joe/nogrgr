{{--
    One page-builder settings field (VEN-032) from a section schema (ThemeSectionLibrary):
    $field, $prefix (input name prefix "sections[<key>][settings]"), $settings, $locales,
    $options (categories / badges / banners / products), $uid (id prefix), $canManage.
--}}
@php
    $name = $field['name'];
    $value = $settings[$name] ?? null;
    $label = __('content::page_builder.fields.'.$name);
    $fieldId = 'pb-'.$uid.'-'.$name;
@endphp
@switch($field['type'])
    @case('text')
    @case('textarea')
    @case('richtext')
        <fieldset class="grid gap-3 rounded-control bg-subtle p-3">
            <legend class="sr-only">{{ $label }}</legend>
            @foreach ($locales as $code => $language)
                @php $localeLabel = __('content::page_builder.locale_field', ['field' => $label, 'language' => $language['native'] ?? $language['name'] ?? strtoupper($code)]); @endphp
                <div lang="{{ $code }}" dir="{{ $language['dir'] ?? 'ltr' }}">
                    @if ($field['type'] === 'richtext')
                        @include('content::admin.partials.rich-editor', ['name' => $prefix.'['.$name.']['.$code.']', 'errorKey' => 'sections.'.$uid.'.settings.'.$name.'.'.$code, 'label' => $localeLabel, 'value' => (string) ($value[$code] ?? ''), 'dir' => $language['dir'] ?? 'ltr', 'required' => false, 'minHeight' => 'min-h-40'])
                    @elseif ($field['type'] === 'textarea')
                        <x-ui::textarea :name="$prefix.'['.$name.']['.$code.']'" :id="$fieldId.'-'.$code" :label="$localeLabel" :value="$value[$code] ?? null" :maxlength="$field['max'] ?? null" rows="4" :disabled="! $canManage" />
                    @else
                        <x-ui::input :name="$prefix.'['.$name.']['.$code.']'" :id="$fieldId.'-'.$code" :label="$localeLabel" :value="$value[$code] ?? null" maxlength="{{ $field['max'] ?? 200 }}" :disabled="! $canManage" :placeholder="__('content::page_builder.theme_text')" />
                    @endif
                </div>
            @endforeach
        </fieldset>
        @break
    @case('number')
        <x-ui::input type="number" :name="$prefix.'['.$name.']'" :id="$fieldId" :label="$label" :value="$value ?? $field['default']" min="{{ $field['min'] }}" max="{{ $field['max'] }}" step="1" :helper="__('content::page_builder.limit_help', ['min' => $field['min'], 'max' => $field['max']])" :disabled="! $canManage" dir="ltr" />
        @break
    @case('select')
        <x-ui::select :name="$prefix.'['.$name.']'" :id="$fieldId" :label="$label" :options="collect($field['options'])->mapWithKeys(fn ($option) => [$option => (\Illuminate\Support\Facades\Lang::has('content::page_builder.options.'.$option) ? __('content::page_builder.options.'.$option) : \Illuminate\Support\Str::headline($option)).($option === $field['default'] ? ' · '.__('content::page_builder.theme_default') : '')])->all()" :selected="$value ?? $field['default']" :search="false" :disabled="! $canManage" />
        @break
    @case('source')
        @php
            $source = $settings['source'] ?? ($field['default'] ?? 'default');
            $sources = collect(\App\Modules\Storefront\Enums\SectionProductSource::options())->when(! empty($field['no_default']), fn ($items) => $items->except('default'))->all();
        @endphp
        <div class="grid gap-3" data-source-group>
            <x-ui::select :name="$prefix.'[source]'" :id="$fieldId" :label="__('content::page_builder.fields.source')" :options="$sources" :selected="$source" :search="false" :disabled="! $canManage" data-source-select />
            <div @class(['hidden' => $source !== 'category']) data-source-for="category">
                @if ($options['categories'] === [])
                    <p class="text-xs text-ink-muted">{{ __('content::page_builder.no_categories') }}</p>
                @else
                    <x-ui::select :name="$prefix.'[category]'" :id="$fieldId.'-category'" :label="__('content::page_builder.fields.category')" :options="$options['categories']" :selected="$settings['category'] ?? null" :placeholder="__('content::page_builder.pick_category')" :disabled="! $canManage" />
                @endif
            </div>
            <div @class(['hidden' => $source !== 'badge']) data-source-for="badge">
                @if ($options['badges'] === [])
                    <p class="text-xs text-ink-muted">{{ __('content::page_builder.no_badges') }}</p>
                @else
                    <x-ui::select :name="$prefix.'[badge]'" :id="$fieldId.'-badge'" :label="__('content::page_builder.fields.badge')" :options="$options['badges']" :selected="$settings['badge'] ?? null" :placeholder="__('content::page_builder.pick_badge')" :disabled="! $canManage" />
                @endif
            </div>
            <div @class(['hidden' => $source !== 'manual']) data-source-for="manual">
                @php $picked = array_map('intval', (array) ($settings['products'] ?? [])); @endphp
                <x-ui::select :name="$prefix.'[products]'" :id="$fieldId.'-products'" multiple :label="__('content::page_builder.fields.products')" :options="array_intersect_key($options['products'], array_flip($picked))" :selected="$picked" :ajax-url="$canManage ? vendor_route('store.page-builder.products') : null" :placeholder="__('content::page_builder.search_products')" :helper="__('content::page_builder.products_help', ['max' => \App\Modules\Storefront\Services\Layouts\StoreLayoutService::MAX_PICKS])" :disabled="! $canManage" />
            </div>
        </div>
        @break
    @case('banners')
        @php $bannerOptions = $options['banners'][$field['placement'] ?? '*'] ?? []; @endphp
        @if ($bannerOptions === [])
            <p class="flex items-center gap-2 text-xs text-ink-muted"><x-ui::icon name="info" :size="14" />{{ __('content::page_builder.no_banners') }} <a href="{{ vendor_route('store.banners.index') }}" class="text-brand underline">{{ __('content::page_builder.manage_banners') }}</a></p>
        @else
            <x-ui::select :name="$prefix.'[banners]'" :id="$fieldId" multiple :label="$label" :options="$bannerOptions" :selected="(array) ($value ?? [])" :placeholder="__('content::page_builder.all_banners')" :helper="__('content::page_builder.banners_help')" :disabled="! $canManage" />
        @endif
        @break
    @case('categories')
        <x-ui::select :name="$prefix.'[categories]'" :id="$fieldId" multiple :label="$label" :options="$options['categories']" :selected="(array) ($value ?? [])" :placeholder="__('content::page_builder.top_categories')" :helper="__('content::page_builder.categories_help')" :disabled="! $canManage" />
        @break
    @case('url')
        <x-ui::input type="url" :name="$prefix.'['.$name.']'" :id="$fieldId" :label="$label" :value="$value" maxlength="500" dir="ltr" :helper="__('content::page_builder.url_help')" :disabled="! $canManage" />
        @break
    @case('image')
        @php $path = is_string($value) ? $value : ''; @endphp
        <div class="grid gap-2" data-image-field>
            <span class="text-[13px] font-medium text-ink">{{ $label }}</span>
            <input type="hidden" name="{{ $prefix }}[{{ $name }}]" value="{{ $path }}" data-image-path>
            <div class="flex items-center gap-3">
                <span class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-control bg-subtle">
                    <img src="{{ $path !== '' ? \App\Modules\Storefront\Services\Layouts\PageBuilderImageStore::url($path) : '' }}" alt="" @class(['size-full object-contain', 'hidden' => $path === '']) data-image-preview>
                    <x-ui::icon name="image" :size="24" @class(['text-ink-muted', 'hidden' => $path !== '']) data-image-empty />
                </span>
                @if ($canManage)
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-2 border border-line px-3 py-1.5 text-sm text-ink hover:bg-subtle">
                            <x-ui::icon name="upload" :size="16" />{{ __('content::page_builder.upload_image') }}
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" data-image-input data-upload-url="{{ vendor_route('store.page-builder.images') }}">
                        </label>
                        <button type="button" @class(['inline-flex items-center gap-1 px-2 py-1.5 text-sm text-danger hover:bg-danger-bg', 'hidden' => $path === '']) data-image-remove><x-ui::icon name="trash" :size="14" />{{ __('content::page_builder.remove_image') }}</button>
                    </div>
                @endif
            </div>
            <p class="text-xs text-ink-muted">{{ __('content::page_builder.image_help') }}</p>
        </div>
        @break
    @case('faq')
        @php $rows = array_values((array) ($value ?? [])); @endphp
        <div class="grid gap-3" data-faq-field data-faq-max="{{ $field['max'] }}">
            <span class="text-[13px] font-medium text-ink">{{ $label }}</span>
            <ol class="grid gap-3" data-faq-rows>
                @foreach ($rows as $rowIndex => $row)
                    @include('storefront::vendor.page-builder._faq-row', ['rowPrefix' => $prefix.'['.$name.']['.$rowIndex.']', 'row' => $row, 'rowId' => $uid.'-'.$rowIndex])
                @endforeach
            </ol>
            <p @class(['text-xs text-ink-muted', 'hidden' => $rows !== []]) data-faq-empty>{{ __('content::page_builder.faq_empty') }}</p>
            @if ($canManage)
                <template data-faq-template>
                    @include('storefront::vendor.page-builder._faq-row', ['rowPrefix' => $prefix.'['.$name.'][__ROW__]', 'row' => [], 'rowId' => $uid.'-__ROW__'])
                </template>
                <x-ui::button type="button" variant="secondary" size="sm" icon="plus" class="w-fit" data-faq-add>{{ __('content::page_builder.faq_add') }}</x-ui::button>
            @endif
        </div>
        @break
@endswitch
