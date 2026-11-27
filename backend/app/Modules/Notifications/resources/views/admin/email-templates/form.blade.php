{{--
    Admin › Settings › Email template edit (ADM-102a, Figma Admin Panel 79:90124,
    decision #118): enable / disable, EN / AR tabs with subject + rich-text body
    (toolbar, documented variable picker), live preview rendered on the server through
    the MAIL-001 layout (desktop / mobile), Send test, Reset to default, last edited.
--}}
@php
    use App\Modules\Notifications\Enums\EmailTemplateEvent;
    use App\Modules\Notifications\Enums\EmailTemplateStatus;

    /** @var \App\Modules\Notifications\Models\EmailTemplate $template */
    $title = $template->name();
    $lastEdited = $template->updated_by_name
        ? __('notifications::templates.last_edited', ['name' => $template->updated_by_name, 'date' => $template->updated_at?->translatedFormat('j M Y, H:i')])
        : ($template->created_at?->eq($template->updated_at) ? __('notifications::templates.never_edited') : __('notifications::templates.last_edited_anonymous', ['date' => $template->updated_at?->translatedFormat('j M Y, H:i')]));
    $samples = collect(EmailTemplateEvent::cases())->mapWithKeys(fn (EmailTemplateEvent $case) => [$case->value => $case->variables()])->all();
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
<x-layouts.panel surface="admin" :title="$title" data-page="admin-email-templates">
    <form
        id="email-template-form"
        method="POST"
        action="{{ route('admin.settings.email-templates.update', $template) }}"
        data-ajax
        data-unsaved-form
        data-email-template-form
        data-preview-url="{{ route('admin.settings.email-templates.render', $template) }}"
        novalidate
        class="grid gap-6"
    >
        @csrf
        @method('PUT')
        <script type="application/json" id="email-template-samples">@json($samples)</script>
        <script type="application/json" id="email-template-i18n">@json(['link' => __('notifications::templates.prompts.link'), 'image' => __('notifications::templates.prompts.image'), 'cell' => __('notifications::templates.prompts.cell')])</script>

        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('notifications::templates.title') => route('admin.settings.email-templates.index'), $title => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="refresh" :data-confirm="__('notifications::templates.reset_message')" :data-confirm-title="__('notifications::templates.reset_title', ['name' => $title])" :data-confirm-label="__('notifications::templates.reset')" data-confirm-method="POST" :data-confirm-url="route('admin.settings.email-templates.reset', $template)">{{ __('notifications::templates.reset') }}</x-ui::button>
                <x-ui::button variant="secondary" icon="mail" data-overlay-open="template-test-modal">{{ __('notifications::templates.send_test') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('notifications::templates.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
            <div class="grid min-w-0 gap-4">
                <x-ui::card>
                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui::input name="event_display" :label="__('notifications::templates.fields.event')" :value="$template->event->value" disabled dir="ltr" :helper="$template->audience->label()" />
                        <input type="hidden" data-template-event value="{{ $template->event->value }}">
                        <x-ui::select name="status" :label="__('notifications::templates.enabled')" :options="EmailTemplateStatus::options()" :selected="$template->event->isMandatory() ? 'active' : $template->status->value" :search="false" required :disabled="$template->event->isMandatory()" :helper="$template->event->isMandatory() ? __('notifications::templates.mandatory') : null" />
                    </div>
                </x-ui::card>

                <x-ui::tabs id="template-locales" variant="segmented" :label="__('notifications::templates.fields.locale')" param="locale" :items="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]">
                    @foreach (['en', 'ar'] as $locale)
                        <x-ui::tab-panel tabs="template-locales" :key="$locale" :active="$loop->first" class="mt-4">
                            <x-ui::card data-email-editor="{{ $locale }}">
                                <div class="grid gap-4" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                                    <x-ui::input :name="'subject['.$locale.']'" :label="__('notifications::templates.fields.subject')" :value="$template->getTranslation('subject', $locale, false)" :required="$locale === 'en'" maxlength="191" data-editor-subject />
                                    <div class="grid gap-1.5" data-field>
                                        <span class="text-sm font-medium text-ink" id="body-{{ $locale }}-label">{{ __('notifications::templates.fields.body') }} @if ($locale === 'en')<span class="text-danger" aria-hidden="true">*</span>@endif</span>
                                        <div class="email-editor overflow-hidden rounded-control border border-line bg-field">
                                            <div class="flex flex-wrap gap-0.5 border-b border-line bg-subtle px-2 py-1.5" role="toolbar" aria-label="{{ __('notifications::templates.toolbar.label') }}" dir="ltr">
                                                @foreach ($toolbar as [$command, $text, $label])
                                                    <button type="button" data-editor-command="{{ $command }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded-chip px-1.5 text-xs font-medium text-ink-secondary hover:bg-surface hover:text-ink" title="{{ __($label) }}" aria-label="{{ __($label) }}">{{ $text }}</button>
                                                @endforeach
                                            </div>
                                            <div class="email-editor-content min-h-48 px-3 py-3 text-sm text-ink outline-none" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="body-{{ $locale }}-label" data-editor-area>{!! $template->getTranslation('body', $locale, false) !!}</div>
                                        </div>
                                        <textarea name="body[{{ $locale }}]" hidden data-editor-input>{{ $template->getTranslation('body', $locale, false) }}</textarea>
                                        <p class="ui-error hidden text-xs text-danger" data-error-for="body.{{ $locale }}"></p>
                                    </div>
                                    @include('notifications::partials.template-variables', ['event' => $template->event])
                                </div>
                            </x-ui::card>
                        </x-ui::tab-panel>
                    @endforeach
                </x-ui::tabs>
            </div>

            @include('notifications::partials.template-preview', ['lastEdited' => $lastEdited])
        </div>
    </form>

    @include('notifications::admin.email-templates._test-modal', ['action' => route('admin.settings.email-templates.test', $template)])
</x-layouts.panel>
