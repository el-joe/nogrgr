<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Repositories\Contracts\ClickRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<AffiliateClick>
 */
class ClickRepository extends BaseRepository implements ClickRepositoryContract
{
    protected function model(): string
    {
        return AffiliateClick::class;
    }

    public function findOpen(int $id, Carbon $since): ?AffiliateClick
    {
        return AffiliateClick::query()
            ->whereKey($id)
            ->whereNull('converted_at')
            ->where('created_at', '>=', $since)
            ->first();
    }
}
