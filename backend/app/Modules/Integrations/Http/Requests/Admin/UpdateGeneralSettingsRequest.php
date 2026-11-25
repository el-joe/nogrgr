<?php

namespace App\Modules\Integrations\Http\Requests\Admin;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Integrations\DTOs\GeneralSettingsData;
use App\Modules\Integrations\Services\GeneralSettingsService;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * General settings (ADM-101). Images use x-ui::image-upload: `<name>[]` = new file,
 * `<name>_keep[]` = "current" keeps the stored one; neither = remove.
 */
class UpdateGeneralSettingsRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('support_phone'))) {
            $this->merge(['support_phone' => null, 'support_phone_country' => null]);

            return;
        }

        $this->normalizePhoneInput('support_phone', 'support_phone_country');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'platform_name' => ['required', 'array'],
            'platform_name.en' => ['required', 'string', 'max:120'],
            'platform_name.ar' => ['required', 'string', 'max:120'],
            'support_email' => ['nullable', 'string', 'email:rfc', 'max:191'],
            'support_phone_country' => ['nullable', 'required_with:support_phone', 'string', 'size:2', $this->phoneRegionRule()],
            'support_phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('support_phone_country')],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'default_language' => ['required', 'string', Rule::in(array_keys(app(GeneralSettingsService::class)->languageOptions()))],
            'archive_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'socials' => ['nullable', 'array'],
        ];

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $rules['socials.'.$network] = ['nullable', 'string', 'url:http,https', 'max:255'];
        }

        foreach (GeneralSettingsService::IMAGES as $image) {
            $rules[$image] = ['nullable', 'array', 'max:1'];
            $rules[$image.'.*'] = $image === 'favicon'
                ? ['image', 'mimes:png', 'max:1024', 'dimensions:min_width=32,min_height=32,ratio=1']
                : ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=64,min_height=16'];
            $rules[$image.'_keep'] = ['nullable', 'array', 'max:1'];
            $rules[$image.'_keep.*'] = ['string', 'in:current'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'platform_name.en' => __('integrations::general.fields.platform_name_en'),
            'platform_name.ar' => __('integrations::general.fields.platform_name_ar'),
            'support_email' => __('integrations::general.fields.support_email'),
            'support_phone' => __('integrations::general.fields.support_phone'),
            'support_phone_country' => __('integrations::general.fields.support_phone'),
            'timezone' => __('integrations::general.fields.timezone'),
            'default_language' => __('integrations::general.fields.default_language'),
            'archive_days' => __('integrations::general.fields.archive_days'),
        ];

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $attributes['socials.'.$network] = __('integrations::general.socials.'.$network);
        }

        foreach (GeneralSettingsService::IMAGES as $image) {
            $attributes[$image] = __('integrations::general.images.'.$image);
            $attributes[$image.'.*'] = __('integrations::general.images.'.$image);
        }

        return $attributes;
    }

    public function payload(): GeneralSettingsData
    {
        $images = [];
        $remove = [];

        foreach (GeneralSettingsService::IMAGES as $image) {
            $file = $this->file($image.'.0');

            if ($file instanceof UploadedFile) {
                $images[$image] = $file;
            } elseif (! in_array('current', (array) $this->input($image.'_keep', []), true)) {
                $remove[] = $image;
            }
        }

        $socials = [];

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $value = $this->input('socials.'.$network);
            $socials[$network] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return new GeneralSettingsData(
            platformName: ['en' => trim((string) $this->input('platform_name.en')), 'ar' => trim((string) $this->input('platform_name.ar'))],
            supportEmail: filled($this->input('support_email')) ? strtolower(trim((string) $this->input('support_email'))) : null,
            supportPhone: filled($this->input('support_phone')) ? (string) $this->input('support_phone') : null,
            supportPhoneCountry: filled($this->input('support_phone_country')) ? (string) $this->input('support_phone_country') : null,
            timezone: (string) $this->input('timezone'),
            defaultLanguage: (string) $this->input('default_language'),
            archiveDays: (int) $this->input('archive_days'),
            socials: $socials,
            images: $images,
            removeImages: $remove,
        );
    }
}
