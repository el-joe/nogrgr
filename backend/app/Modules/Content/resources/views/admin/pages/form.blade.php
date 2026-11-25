{{--
    Admin › Website › Static pages › Create / Edit (ADM-082a, Figma Admin Panel 76:74135):
    per-locale title, rich body, SEO; slug (locked for system pages), target
    (central / storefront / both), compliance flag and status.
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $isNew = ! $page->exists;
    $title = $isNew ? __('content::admin.pages.create_title') : __('content::admin.pages.edit_title', ['name' => $page->title]);
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-content">
    <form method="POST" action="{{ $isNew ? route('admin.content.pages.store') : route('admin.content.pages.update', $page) }}" data-ajax data-unsaved-form data-content-form class="grid gap-6" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-ui::page-header :title="$title"
            :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.pages.title') => route('admin.content.pages.index'), ($isNew ? __('content::admin.pages.new') : $page->title) => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.content.pages.index')">{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::admin.common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_340px]">
            <x-ui::card :title="__('content::admin.common.translations')">
                <x-ui::locale-tabs id="page-i18n">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="page-i18n" :locale="$code">
                            <div class="grid gap-5">
                                <x-ui::input :name="'title['.$code.']'" :label="__('content::admin.pages.fields.title')" :value="$page->getTranslation('title', $code, false)" :required="$code === 'en'" maxlength="200" :dir="$language['dir']" :data-slug-source="$code === 'en' && ! $page->is_system ? 'true' : null" />
                                @include('content::admin.partials.rich-editor', ['name' => 'body['.$code.']', 'errorKey' => 'body.'.$code, 'label' => __('content::admin.pages.fields.body'), 'value' => $page->getTranslation('body', $code, false), 'dir' => $language['dir'], 'required' => $code === 'en', 'minHeight' => 'min-h-96'])
                                @include('content::admin.partials.seo-fields', ['model' => $page, 'code' => $code, 'dir' => $language['dir']])
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
            </x-ui::card>

            <x-ui::card :title="__('content::admin.common.settings')">
                <div class="grid gap-5">
                    <x-ui::select name="status" :label="__('content::admin.pages.fields.status')" :options="$statuses" :selected="$page->status?->value" :search="false" required />
                    <x-ui::select name="target" :label="__('content::admin.pages.fields.target')" :options="$targets" :selected="$page->target?->value" :search="false" required />
                    @if ($page->is_system)
                        <input type="hidden" name="slug" value="{{ $page->slug }}">
                        <x-ui::input name="slug_display" :label="__('content::admin.common.slug')" :value="$page->slug" dir="ltr" disabled :helper="__('content::admin.pages.system')" />
                    @else
                        <x-ui::input name="slug" :label="__('content::admin.common.slug')" :value="$page->slug" :helper="__('content::admin.common.slug_help')" dir="ltr" maxlength="120" data-slug-target />
                    @endif
                    <x-ui::switch name="is_compliance" :label="__('content::admin.pages.fields.compliance')" :description="__('content::admin.pages.fields.compliance_help')" :checked="(bool) $page->is_compliance" />
                </div>
            </x-ui::card>
        </div>
    </form>
</x-layouts.panel>
