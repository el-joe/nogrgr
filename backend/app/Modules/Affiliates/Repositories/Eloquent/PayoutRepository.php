<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<AffiliatePayout>
 */
class PayoutRepository extends BaseRepository implements PayoutRepositoryContract
{
    protected function model(): string
    {
        return AffiliatePayout::class;
    }

    public function listing(array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return AffiliatePayout::query()
            ->with('affiliate')
            ->when(filled($filters['method'] ?? null), static fn (Builder $q) => $q->where('method', (string) $filters['method']))
            ->when(filled($filters['affiliate_id'] ?? null), static fn (Builder $q) => $q->where('affiliate_id', (int) $filters['affiliate_id']))
            ->when($search !== '', static fn (Builder $q) => $q->where(static fn (Builder $inner) => $inner
                ->where('reference', 'like', '%'.$search.'%')
                ->orWhereIn('affiliate_id', Affiliate::withoutTenantScope()->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')->select('id'))));
    }
}
