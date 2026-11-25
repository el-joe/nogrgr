<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Integrations\DTOs\IntegrationSettingsData;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Enums\SettingFieldType;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Save one Integrations card. Rules come from IntegrationSection::fields(): required
 * fields only while the card is enabled; secrets may stay blank when one is stored;
 * the Apple key file must be a readable private key.
 */
class UpdateIntegrationSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function section(): IntegrationSection
    {
        /** @var IntegrationSection $section */
        $section = $this->route('section');

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $section = $this->section();
        $enabled = ! $section->hasEnabledSwitch() || $this->boolean('enabled');
        $rules = [];

        foreach ($section->fields() as $field) {
            $presence = $field->required && $enabled && ! $field->isSecret() && $field->type !== SettingFieldType::Toggle ? 'required' : 'nullable';
            $rules[$field->key] = [$presence, ...$field->rules()];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $section = $this->section();
            $service = app(IntegrationSettingsService::class);
            $enabled = ! $section->hasEnabledSwitch() || $this->boolean('enabled');

            foreach ($section->fields() as $field) {
                if (! $field->isSecret() || $validator->errors()->has($field->key)) {
                    continue;
                }

                $submitted = $field->type === SettingFieldType::File ? $this->file($field->key) : $this->input($field->key);
                $required = $field->required || ($section === IntegrationSection::Ai && $this->aiKeyRequired($field->key));

                if ($required && $enabled && blank($submitted) && ! $service->hasStored($section, $field->key)) {
                    $validator->errors()->add($field->key, __('validation.required', ['attribute' => $this->attributes()[$field->key] ?? $field->key]));
                }

                if ($field->type === SettingFieldType::File && $submitted instanceof UploadedFile && openssl_pkey_get_private((string) $submitted->get()) === false) {
                    $validator->errors()->add($field->key, __('integrations::admin.sections.apple.invalid_key'));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->section()->fields() as $field) {
            $attributes[$field->key] = __('integrations::admin.sections.'.$this->section()->value.'.fields.'.$field->key);
        }

        return $attributes;
    }

    public function payload(): IntegrationSettingsData
    {
        $values = [];
        $files = [];

        foreach ($this->section()->fields() as $field) {
            if ($field->type === SettingFieldType::File) {
                $file = $this->file($field->key);

                if ($file instanceof UploadedFile) {
                    $files[$field->key] = $file;
                }

                continue;
            }

            if ($field->type === SettingFieldType::Toggle) {
                $values[$field->key] = $this->boolean($field->key);

                continue;
            }

            if ($this->has($field->key)) {
                $value = $this->validated($field->key);
                $values[$field->key] = is_scalar($value) || $value === null ? $value : null;
            }
        }

        return new IntegrationSettingsData($this->section(), $values, $files);
    }

    /**
     * An AI provider's key is required as soon as that provider serves a purpose.
     */
    private function aiKeyRequired(string $key): bool
    {
        foreach (TranslationProvider::cases() as $provider) {
            if ($key === $provider->value.'_api_key' && $provider->requiresApiKey()) {
                return in_array($provider->value, [$this->input('provider'), $this->input('custom_provider'), $this->input('paid_provider')], true);
            }
        }

        return false;
    }
}
