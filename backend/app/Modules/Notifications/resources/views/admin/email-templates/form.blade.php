{{--
    Admin › Settings › Email template create / edit (ADM-102a, Figma Admin Panel
    79:90124): EN / AR tabs with subject + rich-text body (toolbar, variable chips),
    live preview with sample values (desktop / mobile), Send test, Save.
--}}
@php
    use App\Modules\Notifications\Enums\EmailTemplateEvent;
    use App\Modules\Notifications\Enums\EmailTemplateStatus;

    /** @var \App\Modules\Notifications\Models\EmailTemplate|null $template */
    $editing = $template !== null;
    $title = $editing ? $template->name() : __('notifications::templates.create_title');
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
        action="{{ $editing ? route('admin.settings.email-templates.update', $template) : route('admin.settings.email-templates.store') }}"
        data-ajax
        data-email-template-form
        novalidate
        class="grid gap-6"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <script type="application/json" id="email-template-samples">@json($samples)</script>
        <script type="application/json" id="email-template-i18n">@json(['link' => __('notifications::templates.prompts.link'), 'image' => __('notifications::templates.prompts.image'), 'cell' => __('notifications::templates.prompts.cell')])</script>

        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('notifications::templates.title') => route('admin.settings.email-templates.index'), $title => null]"
        >
            <x-slot:actions>
                @if ($editing)
                    <x-ui::button variant="secondary" icon="mail" data-overlay-open="template-test-modal">{{ __('notifications::templates.send_test') }}</x-ui::button>
                @endif
                <x-ui::button type="submit" icon="check">{{ __('notifications::templates.save') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
            <div class="grid min-w-0 gap-4">
                <x-ui::card>
                    <div class="grid gap-5 md:grid-cols-2">
                        @if ($editing)
                            <x-ui::input name="event_display" :label="__('notifications::templates.fields.event')" :value="$template->event->value" disabled dir="ltr" :helper="$template->audience->label()" />
                            <input type="hidden" data-template-event value="{{ $template->event->value }}">
                        @else
                            <x-ui::select name="event" :label="__('notifications::templates.fields.event')" required data-template-event>
                                @foreach ($events as $group => $options)
                                    <optgroup label="{{ $group }}">
                                        @foreach ($options as $value => $label)
                                            <option value="{{ $value }}" @selected($event?->value === $value)>{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </x-ui::select>
                        @endif
                        <x-ui::select name="status" :label="__('notifications::templates.fields.status')" :options="EmailTemplateStatus::options()" :selected="$template?->status->value ?? EmailTemplateStatus::Active->value" :search="false" required />
                    </div>
                </x-ui::card>

                <x-ui::tabs id="template-locales" variant="segmented" :label="__('notifications::templates.fields.locale')" param="locale" :items="['en' => __('notifications::templates.locales.en'), 'ar' => __('notifications::templates.locales.ar')]">
                    @foreach (['en', 'ar'] as $locale)
                        <x-ui::tab-panel tabs="template-locales" :key="$locale" :active="$loop->first" class="mt-4">
                            <x-ui::card data-email-editor="{{ $locale }}">
                                <div class="grid gap-4" lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
                                    <x-ui::input :name="'subject['.$locale.']'" :label="__('notifications::templates.fields.subject')" :value="$template?->getTranslation('subject', $locale, false)" :required="$locale === 'en'" maxlength="191" data-editor-subject />
                                    <div class="grid gap-1.5" data-field>
                                        <span class="text-sm font-medium text-ink" id="body-{{ $locale }}-label">{{ __('notifications::templates.fields.body') }} @if ($locale === 'en')<span class="text-danger" aria-hidden="true">*</span>@endif</span>
                                        <div class="email-editor overflow-hidden rounded-control border border-line bg-field">
                                            <div class="flex flex-wrap gap-0.5 border-b border-line bg-subtle px-2 py-1.5" role="toolbar" aria-label="{{ __('notifications::templates.toolbar.label') }}" dir="ltr">
                                                @foreach ($toolbar as [$command, $text, $label])
                                                    <button type="button" data-editor-command="{{ $command }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded-chip px-1.5 text-xs font-medium text-ink-secondary hover:bg-surface hover:text-ink" title="{{ __($label) }}" aria-label="{{ __($label) }}">{{ $text }}</button>
                                                @endforeach
                                            </div>
                                            <div class="email-editor-content min-h-48 px-3 py-3 text-sm text-ink outline-none" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="body-{{ $locale }}-label" data-editor-area>{!! $template?->getTranslation('body', $locale, false) !!}</div>
                                        </div>
                                        <textarea name="body[{{ $locale }}]" hidden data-editor-input>{{ $template?->getTranslation('body', $locale, false) }}</textarea>
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
            </div>

            <x-ui::card class="xl:sticky xl:top-20">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-ink">{{ __('notifications::templates.preview') }}</h2>
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

    @if ($editing)
        @include('notifications::admin.email-templates._test-modal', ['action' => route('admin.settings.email-templates.test', $template)])
    @endif
</x-layouts.panel>
