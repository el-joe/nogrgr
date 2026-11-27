<?php

namespace App\Modules\Platform\Legacy\Steps\Central;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Affiliates (old types tenant → vendor, else independent). Commission: percentage →
 * basis points, fixed → USD cents; balances → minor units. Conversions / payouts are
 * not in the old central DB (empty); the program's ledger starts from these balances.
 */
class AffiliatesStep implements LegacyStepHandler
{
    public function step(): LegacyStep
    {
        return LegacyStep::Affiliates;
    }

    public function run(LegacyContext $context): void
    {
        $writer = $context->central;

        foreach ($context->source->central()->table('affiliates')->orderBy('id')->get() as $row) {
            $context->report->read('central', 'affiliates');
            $id = (int) $row->id;
            $tenantId = $row->tenant_id !== null ? $writer->id('tenant', (string) $row->tenant_id) : null;

            if ($row->tenant_id !== null && $tenantId === null) {
                $context->report->issue(LegacyIssueLevel::Warning, 'central', 'affiliates', $id, 'Vendor affiliate of a store that was not migrated (skipped)');
                $context->report->skipped('central', 'affiliates');

                continue;
            }

            $percentage = $row->commission_type === 'percentage';
            $email = Str::lower(trim((string) $row->email));

            $writer->upsert('affiliate', $id, 'affiliates', [
                'tenant_id' => $tenantId,
                'code' => LegacyUnique::value($writer, 'affiliates', 'code', Str::upper((string) $row->code), $writer->id('affiliate', $id), $id, 32),
                'type' => $tenantId !== null ? 'vendor' : 'independent',
                'name' => (string) $row->name,
                'email' => $email,
                'password' => (string) $row->password,
                'status' => $this->status((string) $row->status)->value,
                'commission_type' => $percentage ? 'percentage' : 'fixed',
                'commission_value' => $percentage ? LegacyValues::bps($row->commission_value) : LegacyValues::minor($row->commission_value),
                'balance' => LegacyValues::minor($row->balance),
                'total_earned' => LegacyValues::minor($row->total_earned),
                'total_paid' => LegacyValues::minor($row->total_paid),
                'payout_method' => $row->paypal_email !== null ? 'paypal' : ($row->bank_iban !== null ? 'bank_transfer' : null),
                'paypal_email' => $row->paypal_email,
                'bank_name' => $row->bank_name !== null ? mb_substr((string) $row->bank_name, 0, 120) : null,
                'bank_account_name' => $row->bank_account !== null ? mb_substr((string) $row->bank_account, 0, 120) : null,
                'bank_iban' => $row->bank_iban !== null ? Crypt::encryptString($context->source->decrypt((string) $row->bank_iban) ?? (string) $row->bank_iban) : null,
                'notes' => $row->notes,
                'approved_at' => $row->approved_at,
                'created_at' => $row->created_at,
            ], $tenantId !== null ? ['tenant_id' => $tenantId] : ['email' => $email]); // a store's partner account may already exist (created at provisioning)
        }
    }

    private function status(string $old): AccountStatus
    {
        return match ($old) {
            'active' => AccountStatus::Active,
            'suspended' => AccountStatus::Suspended,
            default => AccountStatus::Pending,
        };
    }
}
