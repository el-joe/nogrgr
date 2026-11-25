{{-- Admin › Website › Blog categories › Create / Edit (ADM-080a, Figma Admin Panel 76:76659). --}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $isNew = ! $category->exists;
    $title = $isNew ? __('content::admin.categories.create_title') : __('content::admin.categories.edit_title', ['name' => $category->name]);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-content">
    <form method="POST" action="{{ $isNew ? route('admin.content.post-categories.store') : route('admin.content.post-categories.update', $category) }}" data-ajax data-unsaved-form data-content-form class="grid gap-6" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-ui::page-header :title="$title"
            :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.categories.title') => route('admin.content.post-categories.index'), ($isNew ? __('content::admin.categories.new') : $category->name) => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.content.post-categories.index')">{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::admin.common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_320px]">
            <x-ui::card :title="__('content::admin.common.translations')">
                <x-ui::locale-tabs id="category-i18n">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="category-i18n" :locale="$code">
                            <div class="grid gap-5">
                                <x-ui::input :name="'name['.$code.']'" :label="__('content::admin.categories.fields.name')" :value="$category->getTranslation('name', $code, false)" :required="$code === 'en'" maxlength="120" :dir="$language['dir']" :data-slug-source="$code === 'en' ? 'true' : null" />
                                <x-ui::textarea :name="'description['.$code.']'" :label="__('content::admin.categories.fields.description')" :value="$category->getTranslation('description', $code, false)" :rows="3" :maxlength="500" :dir="$language['dir']" />
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
            </x-ui::card>

            <x-ui::card :title="__('content::admin.common.settings')">
                <div class="grid gap-5">
                    <x-ui::input name="slug" :label="__('content::admin.common.slug')" :value="$category->slug" :helper="__('content::admin.common.slug_help')" dir="ltr" maxlength="120" data-slug-target />
                    <x-ui::input name="sort_order" type="number" min="0" :label="__('content::admin.common.sort_order')" :value="$category->sort_order" dir="ltr" />
                    <x-ui::switch name="is_active" :label="__('content::admin.common.active')" :checked="(bool) $category->is_active" />
                </div>
            </x-ui::card>
        </div>
    </form>
</x-layouts.panel>
