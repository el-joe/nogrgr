{{--
    One integration setting input (IntegrationSection field). Secrets are never echoed:
    the input stays empty with the masked value as placeholder (blank = keep).
    @param \App\Modules\Integrations\Enums\IntegrationSection $section
    @param \App\Modules\Integrations\DTOs\SettingField $field
    @param array{value: mixed, masked: string|null, stored: bool} $state
    @param bool $editable
--}}
@php
    use App\Modules\Integrations\Enums\SettingFieldType;

    $base = 'integrations::admin.sections.'.$section->value;
    $label = __($base.'.fields.'.$field->key);
    $helpKey = $base.'.help.'.$field->key;
    $help = __($helpKey) !== $helpKey ? __($helpKey) : null;
    $required = $field->required && $field->type !== SettingFieldType::Toggle;
@endphp
@switch($field->type)
    @case(SettingFieldType::Toggle)
        <x-ui::switch :name="$field->key" :label="$label" :description="$help" :checked="(bool) $state['value']" :disabled="! $editable" class="md:col-span-2" />
        @break
    @case(SettingFieldType::Select)
        <x-ui::select :name="$field->key" :label="$label" :options="$field->options" :selected="$state['value']" :required="$required" :helper="$help" :search="false" :disabled="! $editable" />
        @break
    @case(SettingFieldType::Secret)
        <x-ui::input
            :name="$field->key"
            type="password"
            icon="key"
            :label="$label"
            :required="$required && ! $state['stored']"
            :helper="$state['stored'] ? __('integrations::admin.secret_kept') : ($help ?? __('integrations::admin.secret_helper'))"
            :placeholder="$state['masked'] ?? ''"
            autocomplete="new-password"
            :disabled="! $editable"
            maxlength="{{ $field->maxLength }}"
        />
        @break
    @case(SettingFieldType::File)
        <div class="grid gap-2 md:col-span-2">
            <x-ui::file-upload :name="$field->key" :label="$label" :accept="$field->accept" :required="$required && ! $state['stored']" :disabled="! $editable" :hint="$state['stored'] ? __('integrations::admin.file_stored') : $help" />
        </div>
        @break
    @default
        <x-ui::input
            :name="$field->key"
            :type="match ($field->type) { SettingFieldType::Email => 'email', SettingFieldType::Url => 'url', SettingFieldType::Number => 'number', default => 'text' }"
            :label="$label"
            :value="$state['value']"
            :required="$required"
            :helper="$help"
            dir="ltr"
            maxlength="{{ $field->maxLength }}"
            :disabled="! $editable"
        />
@endswitch
