<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\AffiliateProfileData;
use App\Modules\Affiliates\DTOs\PayoutDetailsData;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Enums\AuditAction;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;

/**
 * Affiliate › Profile (AFF-013 / 013a / 013b): personal details, payout details (only
 * the fields of the chosen method are kept; the IBAN is encrypted by the model cast)
 * and the password.
 */
class AffiliateProfileService extends BaseService
{
    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly Auditor $auditor,
    ) {}

    public function update(Affiliate $affiliate, AffiliateProfileData $data): Affiliate
    {
        $this->affiliates->update($affiliate, [
            'name' => $data->name,
            'phone' => $data->phone,
            'locale' => $data->locale,
        ]);

        return $affiliate;
    }

    public function updatePayoutDetails(Affiliate $affiliate, PayoutDetailsData $data): Affiliate
    {
        $bank = $data->method === PayoutMethod::BankTransfer;

        $this->affiliates->update($affiliate, [
            'payout_method' => $data->method,
            'paypal_email' => $data->method === PayoutMethod::Paypal ? $data->paypalEmail : null,
            'bank_name' => $bank ? $data->bankName : null,
            'bank_account_name' => $bank ? $data->bankAccountName : null,
            'bank_iban' => $bank ? $data->bankIban : null,
        ]);

        return $affiliate;
    }

    public function changePassword(Affiliate $affiliate, string $password): void
    {
        $this->affiliates->update($affiliate, ['password' => $password]);
        $this->auditor->record(AuditAction::PasswordChanged, $affiliate);
    }
}
