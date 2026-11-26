<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Platform\DTOs\ComplianceCenterData;
use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceSection;
use App\Modules\Platform\Services\ComplianceCenterService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Vendor compliance center form (VEN-084), used by "Save" and "Submit for review".
 * Every field may be empty here (a draft); what a submission needs is checked by
 * ComplianceCenterService::submit(). Phones come from intl-tel-input (E.164), the IBAN is
 * normalised (no spaces, upper case) and checked with the ISO 13616 mod-97 checksum.
 */
class ComplianceCenterRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['owner_name', 'owner_id_number', 'legal_name', 'registration_number', 'vat_number', 'address', 'company_email', 'bank_name', 'account_name'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $trimmed[$field] = $value === '' ? null : $value;
            }
        }

        $iban = $this->input('iban');

        if (is_string($iban)) {
            $iban = strtoupper(preg_replace('/[\s-]+/', '', $iban) ?? '');
            $trimmed['iban'] = $iban === '' ? null : $iban;
        }

        $this->merge($trimmed);

        foreach (['owner_phone', 'company_phone'] as $phone) {
            if (is_string($this->input($phone)) && trim($this->input($phone)) === '') {
                $this->merge([$phone => null, $phone.'_country' => null]);
            }

            $this->normalizePhoneInput($phone, $phone.'_country');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $mimes = 'mimes:'.implode(',', ComplianceCenterService::FILE_EXTENSIONS);

        return [
            'account_type' => ['nullable', Rule::enum(ComplianceAccountType::class)],
            'owner_name' => ['nullable', 'string', 'min:2', 'max:255'],
            'owner_id_number' => ['nullable', 'string', 'max:100'],
            'owner_id_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'owner_birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'owner_phone_country' => ['nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'owner_phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('owner_phone_country')],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'registration_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'vat_number' => ['nullable', 'string', 'max:100'],
            'country_id' => ['nullable', 'integer', $this->activeCountryRule()],
            'city_id' => ['nullable', 'integer', $this->cityRule()],
            'address' => ['nullable', 'string', 'max:500'],
            'company_phone_country' => ['nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'company_phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('company_phone_country')],
            'company_email' => ['nullable', 'string', 'email', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', $this->ibanRule()],
            'account_name' => ['nullable', 'string', 'max:255'],
            'accept_terms' => ['nullable', 'boolean'],
            'documents' => ['nullable', 'array:'.implode(',', ComplianceDocumentType::values())],
            'documents.*' => ['nullable', 'file', $mimes, 'max:'.ComplianceCenterService::MAX_FILE_KB],
            'step' => ['nullable', 'string', Rule::in(ComplianceSection::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'documents.*.mimes' => __('platform::vendor_compliance.errors.file_type'),
            'documents.*.max' => __('platform::vendor_compliance.errors.file_size'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['account_type', 'owner_name', 'owner_id_number', 'owner_id_expires_at', 'owner_birth_date', 'owner_phone', 'legal_name', 'registration_number', 'registration_expires_at', 'vat_number', 'country_id', 'city_id', 'address', 'company_phone', 'company_email', 'bank_name', 'iban', 'account_name', 'accept_terms'] as $field) {
            $attributes[$field] = __('platform::vendor_compliance.fields.'.$field);
        }

        $attributes['owner_phone_country'] = $attributes['owner_phone'];
        $attributes['company_phone_country'] = $attributes['company_phone'];

        foreach (ComplianceDocumentType::cases() as $type) {
            $attributes['documents.'.$type->value] = $type->label();
        }

        return $attributes;
    }

    public function step(): ?string
    {
        $step = $this->validated('step');

        return is_string($step) ? $step : null;
    }

    public function toData(): ComplianceCenterData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $files = [];

        foreach (ComplianceDocumentType::cases() as $type) {
            $file = $this->file('documents.'.$type->value);

            if ($file instanceof UploadedFile) {
                $files[$type->value] = $file;
            }
        }

        $string = static fn (string $key): ?string => isset($validated[$key]) && is_string($validated[$key]) ? $validated[$key] : null;
        $integer = static fn (string $key): ?int => isset($validated[$key]) && is_numeric($validated[$key]) ? (int) $validated[$key] : null;
        $countryId = $integer('country_id');

        return new ComplianceCenterData(
            accountType: ComplianceAccountType::tryFrom((string) $string('account_type')),
            ownerName: $string('owner_name'),
            ownerIdNumber: $string('owner_id_number'),
            ownerIdExpiresAt: $string('owner_id_expires_at'),
            ownerBirthDate: $string('owner_birth_date'),
            ownerPhone: $string('owner_phone'),
            legalName: $string('legal_name'),
            registrationNumber: $string('registration_number'),
            registrationExpiresAt: $string('registration_expires_at'),
            vatNumber: $string('vat_number'),
            countryId: $countryId,
            cityId: $countryId !== null ? $integer('city_id') : null,
            address: $string('address'),
            companyPhone: $string('company_phone'),
            companyEmail: $string('company_email'),
            bankName: $string('bank_name'),
            iban: $string('iban'),
            accountName: $string('account_name'),
            acceptTerms: $this->boolean('accept_terms'),
            files: $files,
        );
    }

    private function activeCountryRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_numeric($value) || ! app(ComplianceCenterService::class)->isActiveCountry((int) $value)) {
                $fail('validation.exists')->translate();
            }
        };
    }

    private function cityRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $countryId = $this->input('country_id');

            if (! is_numeric($value) || ! is_numeric($countryId) || ! app(ComplianceCenterService::class)->cityBelongsToCountry((int) $value, (int) $countryId)) {
                $fail('validation.exists')->translate();
            }
        };
    }

    /**
     * ISO 13616: 2 letters, 2 check digits, up to 30 alphanumerics, mod-97 remainder 1.
     */
    private function ibanRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value) !== 1) {
                $fail(__('platform::vendor_compliance.errors.iban'));

                return;
            }

            $numeric = '';

            foreach (str_split(substr($value, 4).substr($value, 0, 4)) as $character) {
                $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            }

            $remainder = 0;

            foreach (str_split($numeric, 7) as $chunk) {
                $remainder = (int) ((string) $remainder.$chunk) % 97;
            }

            if ($remainder !== 1) {
                $fail(__('platform::vendor_compliance.errors.iban'));
            }
        };
    }
}
