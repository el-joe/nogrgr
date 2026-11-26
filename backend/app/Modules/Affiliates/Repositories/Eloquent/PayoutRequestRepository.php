<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<AffiliatePayoutRequest>
 */
class PayoutRequestRepository extends BaseRepository implements PayoutRequestRepositoryContract
{
    protected function model(): string
    {
        return AffiliatePayoutRequest::class;
    }

    public function listing(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return AffiliatePayoutRequest::query()
            ->with(['affiliate', 'payout'])
            ->when(filled($filters['status'] ?? null), static fn (Builder $q) => $q->where('status', (string) $filters['status']))
            ->when($search !== '', static fn (Builder $q) => $q->whereIn('affiliate_id', Affiliate::withoutTenantScope()
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->select('id')));
    }

    public function counts(): array
    {
        $counts = AffiliatePayoutRequest::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $result = [];

        foreach (PayoutRequestStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    public function lock(int $id): AffiliatePayoutRequest
    {
        /** @var AffiliatePayoutRequest $request */
        $request = AffiliatePayoutRequest::query()->whereKey($id)->lockForUpdate()->firstOrFail();

        return $request;
    }
}
