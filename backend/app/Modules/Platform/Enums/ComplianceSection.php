<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Steps of the vendor compliance center (VEN-084, Figma 91:117308), in order.
 */
enum ComplianceSection: string
{
    use HasEnumHelpers;

    case Identity = 'identity';
    case Company = 'company';
    case Bank = 'bank';
    case Documents = 'documents';
    case Terms = 'terms';

    public function icon(): string
    {
        return match ($this) {
            self::Identity => 'users',
            self::Company => 'building',
            self::Bank => 'credit-card',
            self::Documents => 'file',
            self::Terms => 'shield',
        };
    }

    /**
     * Documents uploaded in this step.
     *
     * @return list<ComplianceDocumentType>
     */
    public function documentTypes(): array
    {
        return match ($this) {
            self::Identity => [ComplianceDocumentType::NationalId],
            self::Company => [ComplianceDocumentType::CommercialRegistration],
            self::Bank => [ComplianceDocumentType::BankLetter],
            self::Documents => [ComplianceDocumentType::TaxCertificate, ComplianceDocumentType::TradeLicence, ComplianceDocumentType::Other],
            self::Terms => [],
        };
    }

    public static function forDocument(ComplianceDocumentType $type): self
    {
        foreach (self::cases() as $section) {
            if (in_array($type, $section->documentTypes(), true)) {
                return $section;
            }
        }

        return self::Documents;
    }
}
