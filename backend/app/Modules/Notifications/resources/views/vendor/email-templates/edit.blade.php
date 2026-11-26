{{--
    Vendor › Settings › Email templates › Edit (VEN-080a, Figma Vendor Panel 91:114835 "Edit:
    Order confirmation"): EN / AR tabs with subject + rich-text body (toolbar, variable
    chips), live preview with sample values of this store, Reset to default, Save.
    Shares the editor script with Admin › Email templates (panels/admin/email-templates.js).
--}}
@php
    $name = $row->event->label();
    $title = __('notifications::vendor_templates.edit_title', ['name' => $name]);
    $content = $row->content();
    $index = vendor_route('settings.email-templates.index');
    $source = $row->source();
    $toolbar = [
        ['bold', 'B', 'notifications::templates.toolbar.bold'],
        ['italic', 'I', 'notifications::templates.toolbar.italic'],
        ['underline', 'U', 'notifications::templates.toolbar.underline'],
        ['h2', 'H2', 'notifications::templates.toolbar.h2'],
        ['h3', 'H3', 'notifications::templates.toolbar.h3'],
        ['ul', __('notifications::templates.toolbar.list_short'), 'notifications::templates.toolbar.list'],
        ['ol', '1.', 'notifications::templates.toolbar.ordered'],
        ['quote', __('notifications::templates.toolbar.quote_short'), 'notifications::templates.toolbar.quote'],
        ['code', __('notifications::templates.toolbar.code_short'), 'notifications::templates.toolbar.code'],
        ['link', __('notifications::templates.toolbar.link_short'), 'notifications::templates.toolbar.link'],
        ['image', __('notifications::templates.toolbar.image_short'), 'notifications::templates.toolbar.image'],
        ['table', __('notifications::templates.toolbar.table_short'), 'notifications::templates.toolbar.table'],
        ['variable', '{ }', 'notifications::templates.toolbar.variable'],
    ];
@endphp
<x-vendor::layout :title="$title" page="vendor-email-templates">
    <form id="email-template-form" method="POST" action="{{ vendor_route('settings.email-templates.update', ['event' => $row->event->value]) }}" data-ajax data-unsaved-form data-email-template-form novalidate class="grid gap-6">
        @csrf
        @method('PUT')
        <script type="application/json" id="email-template-samples">@json([$row->event->value => $samples])</script>
        <script type="application/json" id="email-template-i18n">@json(['link' => __('notifications::templates.prompts.link'), 'image' => __('notifications::templates.prompts.image'), 'cell' => __('notifications::templates.prompts.cell')])</script>
        <input type="hidden" data-template-event value="{{ $row->event->value }}">

        <x-ui::page-header :title="$title" :breadcrumbs="[__('notifications::vendor_templates.crumb') => null, __('notifications::vendor_templates.title') => $index, $name => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" :href="$index">{{ __('notifications::vendor_templates.back') }}</x-ui::button>
                @if ($canManage)
                    @if ($row->isCustomized())
                        <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('notifications::vendor_templates.reset_message')" :data-confirm-title="__('notifications::vendor_templates.reset_title', ['name' => $name])" data-confirm-method="DELETE" :data-confirm-label="__('notifications::vendor_templates.reset')" :data-confirm-url="vendor_route('settings.email-templates.reset', ['event' => $row->event->value])">{{ __('notifications::vendor_templates.reset') }}</x-ui::button>
                    @endif
                    <x-ui::button type="submit" icon="check">{{ __('notifications::vendor_templates.save') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        <div @class(['flex items-center gap-3 rounded-card px-4 py-3 text-sm text-ink', 'bg-primary-subtle' => $source === 'custom', 'bg-info-bg' => $source !== 'custom']) role="status">
            <x-ui::icon name="info" :size="16" class="shrink-0" />
            <span>{{ __('notifications::vendor_templates.'.$source.'_note') }}@unless ($canManage) {{ __('notifications::vendor_templates.read_only') }}@endunless</span>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
            <fieldset class="grid min-w-0 gap-4" @disabled(! $canManage)>
                <x-ui::tabs id="template-locales" variant="segmented" :label="__('notifications::templates.fields.locale')" param="locale" :items="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]">
                    @foreach ($locales as $locale)
                        <x-ui::tab-panel tabs="template-locales" :key="$locale" :active="$loop->first" class="mt-4">
                            <x-ui::card data-email-editor="{{ $locale }}">
                                <div class="grid gap-4" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                                    <x-ui::input :name="'subject['.$locale.']'" :label="__('notifications::templates.fields.subject')" :value="$content?->getTranslation('subject', $locale, false)" :required="$locale === 'en'" maxlength="191" data-editor-subject />
                                    <div class="grid gap-1.5" data-field>
                                        <span class="text-sm font-medium text-ink" id="body-{{ $locale }}-label">{{ __('notifications::templates.fields.body') }} @if ($locale === 'en')<span class="text-danger" aria-hidden="true">*</span>@endif</span>
                                        <div class="email-editor overflow-hidden rounded-control border border-line bg-field">
                                            <div class="flex flex-wrap gap-0.5 border-b border-line bg-subtle px-2 py-1.5" role="toolbar" aria-label="{{ __('notifications::templates.toolbar.label') }}" dir="ltr">
                                                @foreach ($toolbar as [$command, $text, $label])
                                                    <button type="button" data-editor-command="{{ $command }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded-chip px-1.5 text-xs font-medium text-ink-secondary hover:bg-surface hover:text-ink" title="{{ __($label) }}" aria-label="{{ __($label) }}">{{ $text }}</button>
                                                @endforeach
                                            </div>
                                            <div class="email-editor-content min-h-48 px-3 py-3 text-sm text-ink outline-none" @if ($canManage) contenteditable="true" @endif role="textbox" aria-multiline="true" aria-labelledby="body-{{ $locale }}-label" data-editor-area>{!! $content?->getTranslation('body', $locale, false) !!}</div>
                                        </div>
                                        <textarea name="body[{{ $locale }}]" hidden data-editor-input>{{ $content?->getTranslation('body', $locale, false) }}</textarea>
                                        <p class="ui-error hidden text-xs text-danger" data-error-for="body.{{ $locale }}"></p>
                                    </div>
                                    <div class="grid gap-2">
                                        <span class="text-xs font-medium text-ink-secondary">{{ __('notifications::templates.variables_help') }}</span>
                                        <div class="flex flex-wrap gap-2" data-variable-chips dir="ltr"></div>
                                    </div>
                                </div>
                            </x-ui::card>
                        </x-ui::tab-panel>
                    @endforeach
                </x-ui::tabs>
            </fieldset>

            <x-ui::card class="xl:sticky xl:top-20">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="ui-card-title text-base font-semibold text-ink">{{ __('notifications::templates.preview') }}</h2>
                    <div class="inline-flex gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('notifications::templates.preview_width') }}">
                        @foreach (['desktop', 'mobile'] as $width)
                            <button type="button" data-preview-width="{{ $width }}" aria-pressed="{{ $width === 'desktop' ? 'true' : 'false' }}" class="inline-flex h-7 items-center rounded-chip px-2.5 text-xs text-ink-secondary aria-pressed:bg-surface aria-pressed:font-medium aria-pressed:text-ink-brand aria-pressed:shadow-card">{{ __('notifications::templates.widths.'.$width) }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-card bg-subtle p-4">
                    <div class="mx-auto w-full rounded-card bg-white p-5 text-[#2b1a14] shadow-card transition-[max-width]" data-preview-pane>
                        <p class="mb-1 text-xs text-[#8a7a72]" data-preview-subject></p>
                        <p class="mb-3 text-lg font-semibold" data-preview-header></p>
                        <div class="email-editor-content text-sm" data-preview-body></div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-ink-secondary">{{ __('notifications::templates.preview_help') }}</p>
            </x-ui::card>
        </div>
    </form>
</x-vendor::layout>
