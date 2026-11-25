{{--
    Admin › Catalog › Categories › Create / Edit (ADM-014a, Figma Admin Panel 65:23866):
    Details (name + description per language, slug, parent, sort position), SEO per
    language, thumbnail (queued webp / avif derivatives) and visibility switches.
    Sticky "Unsaved changes" bar.
--}}
@php
    $isNew = ! $category->exists;
    $title = $isNew ? __('catalog::categories.create_title') : __('catalog::categories.edit_title');
    $thumbnail = $category->thumbnailUrl();
    $value = fn (string $field, string $code) => $isNew ? null : $category->getTranslation($field, $code, false);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-catalog">
    <form
        id="category-form"
        method="POST"
        action="{{ $isNew ? route('admin.catalog.categories.store') : route('admin.catalog.categories.update', $category) }}"
        enctype="multipart/form-data"
        data-ajax
        data-unsaved-form
        data-category-form
        class="grid gap-6"
        novalidate
    >
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::categories.title') => route('admin.catalog.categories.index'), ($isNew ? __('catalog::categories.new') : $category->name) => null]"
        >
            @unless ($isNew)
                <x-slot:status><x-ui::status :status="$category->status->value">{{ $category->status->label() }}</x-ui::status></x-slot:status>
            @endunless
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.catalog.categories.index')">{{ __('catalog::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('catalog::common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="grid gap-6">
                <x-ui::card :title="__('catalog::categories.sections.details')">
                    <div class="grid gap-5">
                        <x-ui::locale-tabs id="category-i18n" :locales="$locales">
                            @foreach ($locales as $code => $language)
                                <x-ui::locale-pane parent="category-i18n" :locale="$code">
                                    <x-ui::input :name="'name['.$code.']'" :label="__('catalog::categories.fields.name')" :value="$value('name', $code)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="120" :dir="$language['dir']" :data-slug-source="$code === 'en' ? '' : null" />
                                    <x-ui::textarea :name="'description['.$code.']'" :label="__('catalog::categories.fields.description')" :value="$value('description', $code)" :rows="4" :maxlength="2000" :dir="$language['dir']" />
                                </x-ui::locale-pane>
                            @endforeach
                        </x-ui::locale-tabs>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui::input name="slug" :label="__('catalog::categories.fields.slug')" :value="$category->slug" :helper="__('catalog::categories.fields.slug_help')" maxlength="180" dir="ltr" data-slug-target :data-slug-locked="$isNew ? null : ''" />
                            <x-ui::input name="sort_order" type="number" min="0" max="65535" :label="__('catalog::categories.fields.sort_order')" :value="$isNew ? null : $category->sort_order" dir="ltr" />
                            <x-ui::select name="parent_id" class="sm:col-span-2" :label="__('catalog::categories.fields.parent')" :options="$parents" :selected="$category->parent_id" :placeholder="__('catalog::categories.fields.parent_none')" allow-clear />
                        </div>
                    </div>
                </x-ui::card>

                <x-ui::card :title="__('catalog::categories.sections.seo')">
                    <x-ui::locale-tabs id="category-seo" :locales="$locales">
                        @foreach ($locales as $code => $language)
                            <x-ui::locale-pane parent="category-seo" :locale="$code">
                                <x-ui::input :name="'meta_title['.$code.']'" :label="__('catalog::categories.fields.meta_title')" :value="$value('meta_title', $code)" maxlength="160" :dir="$language['dir']" />
                                <x-ui::textarea :name="'meta_description['.$code.']'" :label="__('catalog::categories.fields.meta_description')" :value="$value('meta_description', $code)" :rows="3" :maxlength="320" :dir="$language['dir']" />
                            </x-ui::locale-pane>
                        @endforeach
                    </x-ui::locale-tabs>
                </x-ui::card>
            </div>

            <div class="grid gap-6">
                <x-ui::card :title="__('catalog::categories.sections.thumbnail')">
                    <x-ui::image-upload name="thumbnail" :max="1" accept="image/jpeg,image/png,image/webp" :images="$thumbnail ? [['id' => 'current', 'url' => $thumbnail]] : []" :helper="__('catalog::categories.fields.thumbnail_help')" :label="__('catalog::categories.fields.thumbnail')" />
                </x-ui::card>

                <x-ui::card :title="__('catalog::categories.sections.visibility')">
                    <div class="grid gap-4">
                        <x-ui::switch name="status" value="active" unchecked-value="inactive" :label="__('catalog::categories.fields.active')" :checked="$isNew || $category->status === \App\Modules\Catalog\Enums\RecordStatus::Active" />
                        <x-ui::switch name="is_featured" :label="__('catalog::categories.fields.featured')" :checked="(bool) $category->is_featured" />
                        <x-ui::switch name="show_in_menu" :label="__('catalog::categories.fields.show_in_menu')" :checked="$isNew || $category->show_in_menu" />
                    </div>
                </x-ui::card>
            </div>
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
