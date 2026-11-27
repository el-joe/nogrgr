{{--
    Vendor › Settings › Email templates › Edit (VEN-080a, Figma Vendor Panel 91:114835 "Edit:
    Order confirmation"): EN / AR tabs with subject + rich-text body (toolbar, variable
    picker), enable / disable, live preview rendered on the server through the store
    variant of the MAIL-001 layout, Send test (to the signed-in member), Reset to default,
    last edited by / at, Save (decision #118). Empty store + platform copies start from
    the code default.
    Shares the editor script with Admin › Email templates (panels/admin/email-templates.js).
--}}
@php
    $name = $row->event->label();
    $title = __('notifications::vendor_templates.edit_title', ['name' => $name]);
    $index = vendor_route('settings.email-templates.index');
    $source = $row->source();
    $staff = auth('vendor')->user();
    $lastEdited = $row->isCustomized() && $row->updatedAt() !== null
        ? ($row->editorName()
            ? __('notifications::templates.last_edited', ['name' => $row->editorName(), 'date' => $row->updatedAt()->translatedFormat('j M Y, H:i')])
            : __('notifications::templates.last_edited_anonymous', ['date' => $row->updatedAt()->translatedFormat('j M Y, H:i')]))
        : null;
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
    <form id="email-template-form" method="POST" action="{{ vendor_route('settings.email-templates.update', ['event' => $row->event->value]) }}" data-ajax data-unsaved-form data-email-template-form @if ($canManage) data-preview-url="{{ vendor_route('settings.email-templates.render', ['event' => $row->event->value]) }}" @endif novalidate class="grid gap-6">
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
                    <x-ui::button variant="secondary" icon="mail" data-overlay-open="template-test-modal">{{ __('notifications::templates.send_test') }}</x-ui::button>
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
                <x-ui::card>
                    <x-ui::select name="status" :label="__('notifications::templates.enabled')" :options="\App\Modules\Notifications\Enums\EmailTemplateStatus::options()" :selected="$row->isEnabled() || $row->event->isMandatory() ? 'active' : 'inactive'" :search="false" required :disabled="$row->event->isMandatory()" :helper="$row->event->isMandatory() ? __('notifications::templates.mandatory') : null" />
                </x-ui::card>
                <x-ui::tabs id="template-locales" variant="segmented" :label="__('notifications::templates.fields.locale')" param="locale" :items="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]">
                    @foreach ($locales as $locale)
                        <x-ui::tab-panel tabs="template-locales" :key="$locale" :active="$loop->first" class="mt-4">
                            <x-ui::card data-email-editor="{{ $locale }}">
                                <div class="grid gap-4" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                                    <x-ui::input :name="'subject['.$locale.']'" :label="__('notifications::templates.fields.subject')" :value="$row->text('subject', $locale)" :required="$locale === 'en'" maxlength="191" data-editor-subject />
                                    <div class="grid gap-1.5" data-field>
                                        <span class="text-sm font-medium text-ink" id="body-{{ $locale }}-label">{{ __('notifications::templates.fields.body') }} @if ($locale === 'en')<span class="text-danger" aria-hidden="true">*</span>@endif</span>
                                        <div class="email-editor overflow-hidden rounded-control border border-line bg-field">
                                            <div class="flex flex-wrap gap-0.5 border-b border-line bg-subtle px-2 py-1.5" role="toolbar" aria-label="{{ __('notifications::templates.toolbar.label') }}" dir="ltr">
                                                @foreach ($toolbar as [$command, $text, $label])
                                                    <button type="button" data-editor-command="{{ $command }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded-chip px-1.5 text-xs font-medium text-ink-secondary hover:bg-surface hover:text-ink" title="{{ __($label) }}" aria-label="{{ __($label) }}">{{ $text }}</button>
                                                @endforeach
                                            </div>
                                            <div class="email-editor-content min-h-48 px-3 py-3 text-sm text-ink outline-none" @if ($canManage) contenteditable="true" @endif role="textbox" aria-multiline="true" aria-labelledby="body-{{ $locale }}-label" data-editor-area>{!! $row->text('body', $locale) !!}</div>
                                        </div>
                                        <textarea name="body[{{ $locale }}]" hidden data-editor-input>{{ $row->text('body', $locale) }}</textarea>
                                        <p class="ui-error hidden text-xs text-danger" data-error-for="body.{{ $locale }}"></p>
                                    </div>
                                    @include('notifications::partials.template-variables', ['event' => $row->event])
                                </div>
                            </x-ui::card>
                        </x-ui::tab-panel>
                    @endforeach
                </x-ui::tabs>
            </fieldset>

            @if ($canManage)
                @include('notifications::partials.template-preview', ['lastEdited' => $lastEdited])
            @else
                <x-ui::card class="xl:sticky xl:top-20">
                    <h2 class="ui-card-title mb-3 text-base font-semibold text-ink">{{ __('notifications::templates.preview') }}</h2>
                    <iframe title="{{ __('notifications::templates.preview') }}" src="{{ vendor_route('settings.email-templates.preview', ['event' => $row->event->value]) }}" sandbox loading="lazy" class="h-[560px] w-full rounded-control border border-line bg-white"></iframe>
                    @if ($lastEdited)
                        <p class="mt-3 text-xs text-ink-secondary">{{ $lastEdited }}</p>
                    @endif
                </x-ui::card>
            @endif
        </div>
    </form>

    @if ($canManage)
        @include('notifications::admin.email-templates._test-modal', ['action' => vendor_route('settings.email-templates.test', ['event' => $row->event->value]), 'email' => (string) $staff?->email])
    @endif
</x-vendor::layout>
