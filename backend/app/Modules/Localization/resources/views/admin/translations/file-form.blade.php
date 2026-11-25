{{--
    Create a panel translation file (ADM-092): location (app lang or a module), file name
    and its first key. Stored as central overrides, so it survives deploys.
--}}
<form id="translation-form" method="POST" action="{{ route('admin.localization.translations.files.store') }}" data-ajax novalidate class="grid gap-5" data-modal-title="{{ __('localization::translations.file_title') }}" data-submit-label="{{ __('localization::translations.create') }}">
    @csrf
    <x-ui::select name="namespace" :label="__('localization::translations.fields.location')" :options="['' => __('localization::translations.fields.location_app')] + $namespaces" selected="" />
    <x-ui::input name="name" :label="__('localization::translations.fields.file_name')" :helper="__('localization::translations.hints.file_name')" required maxlength="48" dir="ltr" autocomplete="off" data-autofocus />
    <x-ui::input name="key" :label="__('localization::translations.fields.key')" :helper="__('localization::translations.hints.key')" required maxlength="191" dir="ltr" autocomplete="off" />
    @foreach ($locales as $code => $name)
        <x-ui::textarea
            :name="'values['.$code.']'"
            :label="__('localization::translations.fields.value_in', ['language' => $name.' ('.$code.')'])"
            :rows="2"
            :maxlength="\App\Modules\Localization\Http\Requests\Admin\TranslationValuesRequest::VALUE_MAX"
            :required="$code === 'en'"
            dir="{{ \App\Shared\Support\Locale::direction($code) }}"
            lang="{{ $code }}"
        />
    @endforeach
</form>
