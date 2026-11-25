{{-- Admin › Website › FAQs › Create / Edit (ADM-083a, Figma Admin Panel 76:76659). --}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $isNew = ! $faq->exists;
    $title = $isNew ? __('content::admin.faqs.create_title') : __('content::admin.faqs.edit_title');
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-content">
    <form method="POST" action="{{ $isNew ? route('admin.content.faqs.store') : route('admin.content.faqs.update', $faq) }}" data-ajax data-unsaved-form data-content-form class="grid gap-6" novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        <x-ui::page-header :title="$title"
            :breadcrumbs="[__('navigation.admin.website') => null, __('content::admin.faqs.title') => route('admin.content.faqs.index'), $title => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="route('admin.content.faqs.index')">{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('content::admin.common.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_320px]">
            <x-ui::card :title="__('content::admin.common.translations')">
                <x-ui::locale-tabs id="faq-i18n">
                    @foreach ($locales as $code => $language)
                        <x-ui::locale-pane parent="faq-i18n" :locale="$code">
                            <div class="grid gap-5">
                                <x-ui::input :name="'question['.$code.']'" :label="__('content::admin.faqs.fields.question')" :value="$faq->getTranslation('question', $code, false)" :required="$code === 'en'" maxlength="300" :dir="$language['dir']" />
                                @include('content::admin.partials.rich-editor', ['name' => 'answer['.$code.']', 'errorKey' => 'answer.'.$code, 'label' => __('content::admin.faqs.fields.answer'), 'value' => $faq->getTranslation('answer', $code, false), 'dir' => $language['dir'], 'required' => $code === 'en', 'minHeight' => 'min-h-40'])
                            </div>
                        </x-ui::locale-pane>
                    @endforeach
                </x-ui::locale-tabs>
            </x-ui::card>

            <x-ui::card :title="__('content::admin.common.settings')">
                <div class="grid gap-5">
                    <x-ui::select name="group" :label="__('content::admin.faqs.fields.group')" :options="$groups" :selected="$faq->group?->value" :search="false" required />
                    <x-ui::switch name="is_active" :label="__('content::admin.faqs.fields.active')" :checked="(bool) $faq->is_active" />
                </div>
            </x-ui::card>
        </div>
    </form>
</x-layouts.panel>
