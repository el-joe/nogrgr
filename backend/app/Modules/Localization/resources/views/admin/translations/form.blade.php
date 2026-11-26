{{--
    Edit a key (PUT) or add a key (POST) in every editor language, loaded into
    #translation-modal and submitted by its footer button (form="translation-form").
--}}
@php
    /** @var \App\Modules\Localization\Support\TranslationFile $file */
    $editing = $entry !== null;
    $title = $editing ? __('localization::translations.edit_title', ['key' => $entry['key']]) : __('localization::translations.add_title', ['file' => $file->label()]);
@endphp
<form id="translation-form" method="POST" action="{{ $editing ? route('admin.localization.translations.update') : route('admin.localization.translations.store') }}" data-ajax novalidate class="grid gap-5" data-modal-title="{{ $title }}" data-modal-subtitle="{{ $file->label() }}" data-submit-label="{{ __('localization::translations.save') }}">
    @csrf
    @if ($editing)
        @method('PUT')
        <input type="hidden" name="key" value="{{ $entry['key'] }}">
    @endif
    <input type="hidden" name="file" value="{{ $file->id }}">
    @if ($editing)
        <div class="grid gap-1 text-sm">
            <span class="text-ink-secondary">{{ __('localization::translations.fields.key') }}</span>
            <span class="font-medium text-ink" dir="ltr">{{ $entry['key'] }}</span>
        </div>
    @else
        <x-ui::input name="key" :label="__('localization::translations.fields.key')" :helper="__('localization::translations.hints.key')" required maxlength="191" dir="ltr" autocomplete="off" data-autofocus />
    @endif
    @if ($file->isStorefront())
        <p class="text-xs text-ink-secondary">{{ __('localization::translations.hints.storefront') }}</p>
    @endif
    @php($canSuggest = $editing && ! ($entry['locked'] ?? false) && filled($entry['values']['en'] ?? null))
    @foreach ($locales as $code => $name)
        @php($disk = $entry['disk'][$code] ?? null)
        @if ($canSuggest && $code !== 'en')
            {{-- ADM-092a one-key AI suggestion (custom provider): fills the textarea below, nothing is saved. --}}
            <div class="-mb-3 flex justify-end">
                <x-ui::button size="sm" variant="ghost" icon="sparkles" data-ai-suggest :data-url="route('admin.localization.ai-translation.static-keys.suggest')" :data-file="$file->id" :data-key="$entry['key']" :data-locale="$code" :data-suggested="__('ai::admin_translation.actions.suggested')" :aria-label="__('ai::admin_translation.actions.suggest_label', ['language' => $name])">{{ __('ai::admin_translation.actions.suggest') }}</x-ui::button>
            </div>
        @endif
        <x-ui::textarea
            :name="'values['.$code.']'"
            :label="__('localization::translations.fields.value_in', ['language' => $name.' ('.$code.')'])"
            :value="$entry['values'][$code] ?? null"
            :rows="2"
            :maxlength="\App\Modules\Localization\Http\Requests\Admin\TranslationValuesRequest::VALUE_MAX"
            :required="$code === 'en' && ! $editing"
            :helper="$disk !== null ? \Illuminate\Support\Str::limit(__('localization::translations.fields.disk_value', ['value' => $disk]), 160) : null"
            dir="{{ \App\Shared\Support\Locale::direction($code) }}"
            lang="{{ $code }}"
        />
    @endforeach
    <p class="text-xs text-ink-secondary">{{ __('localization::translations.hints.empty_value') }}</p>
</form>
