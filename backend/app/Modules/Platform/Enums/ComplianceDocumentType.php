<?php

namespace App\Modules\Platform\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

enum ComplianceDocumentType: string
{
    use HasEnumHelpers;

    case CommercialRegistration = 'commercial_registration';
    case NationalId = 'national_id';
    case TaxCertificate = 'tax_certificate';
    case TradeLicence = 'trade_licence';
    case BankLetter = 'bank_letter';
    case Other = 'other';

    /**
     * Short code for list cells ("CR, ID, IBAN").
     */
    public function shortLabel(): string
    {
        $key = 'platform::enums.compliance_document_type_short.'.$this->value;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $this->label();
    }

    /**
     * Required for a low-risk review.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::CommercialRegistration, self::NationalId];
    }
}
