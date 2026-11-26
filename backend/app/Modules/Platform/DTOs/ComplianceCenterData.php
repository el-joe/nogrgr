<?php

namespace App\Modules\Platform\DTOs;

use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Shared\DTOs\BaseDTO;
use Illuminate\Http\UploadedFile;

/**
 * Vendor compliance center form (VEN-084): identity, company, bank, new document files
 * (one per type, replacing the previous file) and the terms acceptance.
 */
final class ComplianceCenterData extends BaseDTO
{
    /**
     * @param  array<string, UploadedFile>  $files  document type value => uploaded file
     */
    public function __construct(
        public readonly ?ComplianceAccountType $accountType = null,
        public readonly ?string $ownerName = null,
        public readonly ?string $ownerIdNumber = null,
        public readonly ?string $ownerIdExpiresAt = null,
        public readonly ?string $ownerBirthDate = null,
        public readonly ?string $ownerPhone = null,
        public readonly ?string $legalName = null,
        public readonly ?string $registrationNumber = null,
        public readonly ?string $registrationExpiresAt = null,
        public readonly ?string $vatNumber = null,
        public readonly ?int $countryId = null,
        public readonly ?int $cityId = null,
        public readonly ?string $address = null,
        public readonly ?string $companyPhone = null,
        public readonly ?string $companyEmail = null,
        public readonly ?string $bankName = null,
        public readonly ?string $iban = null,
        public readonly ?string $accountName = null,
        public readonly bool $acceptTerms = false,
        public readonly array $files = [],
    ) {}

    public function file(ComplianceDocumentType $type): ?UploadedFile
    {
        return $this->files[$type->value] ?? null;
    }

    /**
     * Profile columns written by the form (the encrypted values included).
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        $company = $this->accountType === ComplianceAccountType::Company;

        return [
            'account_type' => $this->accountType,
            'owner_name' => $this->ownerName,
            'owner_id_number' => $this->ownerIdNumber,
            'owner_id_expires_at' => $this->ownerIdExpiresAt,
            'owner_birth_date' => $this->ownerBirthDate,
            'owner_phone' => $this->ownerPhone,
            'legal_name' => $company ? $this->legalName : $this->ownerName,
            'registration_number' => $company ? $this->registrationNumber : null,
            'registration_expires_at' => $company ? $this->registrationExpiresAt : null,
            'vat_number' => $this->vatNumber,
            'country_id' => $this->countryId,
            'city_id' => $this->cityId,
            'address' => $this->address,
            'company_phone' => $this->companyPhone,
            'company_email' => $this->companyEmail,
            'bank_name' => $this->bankName,
            'iban' => $this->iban,
            'account_name' => $this->accountName,
        ];
    }
}
