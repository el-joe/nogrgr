<?php

namespace App\Modules\Notifications\Http\Requests\Admin;

use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Modules\Notifications\DTOs\MailLayoutSettingsData;
use App\Modules\Notifications\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Admin › Settings › Email layout (MAIL-001): colours (#rrggbb), footer per locale,
 * address, help / privacy links, social links and the logo — `logo[]` uploads a new one,
 * `logo_keep[]` = "current" keeps the stored one, neither = back to the platform logo.
 */
class UpdateMailLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer' => ['nullable', 'array'],
            'address' => ['nullable', 'string', 'max:191'],
            'help_url' => ['nullable', 'url:https,http', 'max:255'],
            'privacy_url' => ['nullable', 'url:https,http', 'max:255'],
            'show_socials' => ['nullable', 'boolean'],
            'socials' => ['nullable', 'array'],
            'logo' => ['nullable', 'array', 'max:1'],
            'logo.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_keep' => ['nullable', 'array', 'max:1'],
            'logo_keep.*' => ['string', 'in:current'],
        ];

        foreach (EmailTemplate::LOCALES as $locale) {
            $rules['footer.'.$locale] = ['nullable', 'string', 'max:300'];
        }

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $rules['socials.'.$network] = ['nullable', 'url:https,http', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'primary_color' => __('notifications::layout.fields.primary_color'),
            'background_color' => __('notifications::layout.fields.background_color'),
            'address' => __('notifications::layout.fields.address'),
            'help_url' => __('notifications::layout.fields.help_url'),
            'privacy_url' => __('notifications::layout.fields.privacy_url'),
            'logo.0' => __('notifications::layout.fields.logo'),
        ];

        foreach (EmailTemplate::LOCALES as $locale) {
            $attributes['footer.'.$locale] = __('notifications::layout.fields.footer_'.$locale);
        }

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $attributes['socials.'.$network] = (string) __('notifications::mail_layout.socials.'.$network);
        }

        return $attributes;
    }

    public function toData(): MailLayoutSettingsData
    {
        $socials = [];

        foreach (GeneralSettingsService::SOCIALS as $network) {
            $url = trim((string) $this->input('socials.'.$network, ''));
            $socials[$network] = $url !== '' ? $url : null;
        }

        $footer = [];

        foreach (EmailTemplate::LOCALES as $locale) {
            $footer[$locale] = (string) $this->input('footer.'.$locale, '');
        }

        $logo = $this->file('logo.0');

        return new MailLayoutSettingsData(
            primaryColor: (string) $this->input('primary_color'),
            backgroundColor: (string) $this->input('background_color'),
            footer: $footer,
            socials: $socials,
            showSocials: $this->boolean('show_socials'),
            logo: $logo instanceof UploadedFile ? $logo : null,
            removeLogo: ! $logo instanceof UploadedFile && ! in_array('current', (array) $this->input('logo_keep', []), true),
            address: filled($this->input('address')) ? trim((string) $this->input('address')) : null,
            helpUrl: filled($this->input('help_url')) ? (string) $this->input('help_url') : null,
            privacyUrl: filled($this->input('privacy_url')) ? (string) $this->input('privacy_url') : null,
        );
    }
}
