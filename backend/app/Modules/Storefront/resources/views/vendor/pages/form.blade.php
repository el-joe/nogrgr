{{--
    Vendor › Your store › Pages › Create / Edit (VEN-033a, Figma Vendor Panel 85:106165 "Edit:
    About us"): a language tab per store language (title, rich-text body, meta description),
    slug (custom pages; system pages keep theirs), published and "show in footer".
--}}
@php
    use App\Modules\Storefront\Enums\StorePageType;

    $isNew = ! $page->exists;
    $system = $page->type === StorePageType::System;
    $name = $isNew ? '' : (string) ($page->title ?: $page->slug);
    $title = $isNew ? __('content::store_pages.create_title') : __('content::store_pages.edit_title', ['title' => $name]);
    $index = vendor_route('store.pages.index');
    $required = \App\Modules\Catalog\Support\CatalogLocales::REQUIRED;
@endphp
<x-vendor::layout :title="$title" page="vendor-store-pages">
    <form method="POST" action="{{ $isNew ? vendor_route('store.pages.store') : vendor_route('store.pages.update', ['page' => $page->id]) }}" data-ajax data-unsaved-form data-content-form class="grid gap-6" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-ui::page-header :title="$title" :breadcrumbs="[__('content::store_pages.crumb') => null, __('content::store_pages.title') => $index, ($isNew ? __('content::store_pages.create_title') : $name) => null]">
            <x-slot:actions>
                @if (! $isNew && $storeUrl && $page->is_active)
                    <x-ui::button variant="secondary" icon="eye" :href="$storeUrl.'/pages/'.$page->slug" target="_blank" rel="noopener">{{ __('content::store_pages.view') }}</x-ui::button>
                @endif
                <x-ui::button variant="secondary" :href="$index">{{ __('content::store_pages.back') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::store_pages.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card :title="__('content::store_pages.languages')">
                <x-ui::locale-tabs id="store-page-i18n" :locales="$locales">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="store-page-i18n" :locale="$code" :active="array_key_first($locales)">
                            <div class="grid gap-5" lang="{{ $code }}">
                                <x-ui::input :name="'title['.$code.']'" :label="__('content::store_pages.fields.title')" :value="$page->exists ? $page->getTranslation('title', $code, false) : null" :required="in_array($code, $required, true)" maxlength="150" :dir="$language['dir'] ?? 'ltr'" :data-slug-source="$code === 'en' && ! $system && $isNew ? 'true' : null" />
                                @include('content::admin.partials.rich-editor', ['name' => 'body['.$code.']', 'errorKey' => 'body.'.$code, 'label' => __('content::store_pages.fields.body'), 'value' => $page->exists ? $page->getTranslation('body', $code, false) : '', 'dir' => $language['dir'] ?? 'ltr', 'required' => false, 'minHeight' => 'min-h-80'])
                                <x-ui::textarea :name="'meta_description['.$code.']'" :label="__('content::store_pages.fields.meta_description')" :value="$page->exists ? $page->getTranslation('meta_description', $code, false) : null" maxlength="160" rows="2" :helper="__('content::store_pages.fields.meta_help')" :dir="$language['dir'] ?? 'ltr'" />
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
            </x-ui::card>

            <x-ui::card :title="__('content::store_pages.settings')" class="xl:sticky xl:top-20">
                <div class="grid gap-5">
                    @if ($system)
                        <x-ui::input name="slug_display" :label="__('content::store_pages.fields.slug')" :value="$page->slug" dir="ltr" disabled :helper="__('content::store_pages.fields.slug_system')" />
                    @else
                        <x-ui::input name="slug" :label="__('content::store_pages.fields.slug')" :value="$page->slug" dir="ltr" maxlength="120" :helper="__('content::store_pages.fields.slug_help')" data-slug-target />
                    @endif
                    @if (! $isNew && $storeUrl)
                        <div class="grid gap-1">
                            <span class="text-[13px] font-medium text-ink">{{ __('content::store_pages.url') }}</span>
                            <span class="truncate text-sm text-ink-secondary" dir="ltr">{{ $storeUrl }}/pages/{{ $page->slug }}</span>
                        </div>
                    @endif
                    <x-ui::switch name="is_active" :label="__('content::store_pages.fields.is_active')" :description="__('content::store_pages.fields.is_active_help')" :checked="(bool) $page->is_active" />
                    <x-ui::switch name="show_in_footer" :label="__('content::store_pages.fields.show_in_footer')" :description="__('content::store_pages.fields.show_in_footer_help')" :checked="(bool) $page->show_in_footer" />
                </div>
            </x-ui::card>
        </div>
    </form>
</x-vendor::layout>
