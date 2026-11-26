<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\DTOs\SsoResult;
use App\Modules\Affiliates\Enums\SsoFailure;
use App\Modules\Affiliates\Models\AffiliateSsoToken;
use App\Modules\Affiliates\Repositories\Contracts\SsoTokenRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Vendor panel → affiliate panel single sign-on (VEN-055 "Open affiliate dashboard",
 * AFF-007). issueFor() makes a random 64-character token valid for TTL_SECONDS and
 * returns the central landing URL; consume() accepts it exactly once.
 */
class AffiliateSsoService extends BaseService
{
    public const TTL_SECONDS = 120;

    public function __construct(
        private readonly SsoTokenRepositoryContract $tokens,
        private readonly AffiliateAccountService $accounts,
    ) {}

    /**
     * The landing URL for the store's vendor affiliate (created when missing).
     */
    public function issueFor(Tenant $tenant): string
    {
        $affiliate = $this->accounts->createForTenant($tenant);
        $token = Str::random(64);

        $this->tokens->create([
            'affiliate_id' => $affiliate->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return route('affiliate.sso', ['token' => $token]);
    }

    public function consume(string $token): SsoResult
    {
        if (strlen($token) !== 64) {
            return SsoResult::failed(SsoFailure::Invalid);
        }

        $result = $this->transaction(function () use ($token): SsoResult {
            $record = $this->tokens->lockByHash(hash('sha256', $token));

            if (! $record instanceof AffiliateSsoToken) {
                return SsoResult::failed(SsoFailure::Invalid);
            }

            if ($record->used_at !== null) {
                return SsoResult::failed(SsoFailure::Used);
            }

            if ($record->expires_at->isPast()) {
                return SsoResult::failed(SsoFailure::Expired);
            }

            $affiliate = Affiliate::withoutTenantScope()->find($record->affiliate_id);

            if (! $affiliate instanceof Affiliate || ! $affiliate->isActive()) {
                return SsoResult::failed(SsoFailure::Invalid);
            }

            $this->tokens->update($record, ['used_at' => now()]);

            return new SsoResult($affiliate);
        }, (new AffiliateSsoToken)->getConnectionName());

        if ($result->affiliate !== null) {
            $this->accounts->ensurePanelRole($result->affiliate);
        }

        $this->tokens->prune();

        return $result;
    }
}
