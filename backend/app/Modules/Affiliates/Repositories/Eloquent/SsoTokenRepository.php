<?php

namespace App\Modules\Affiliates\Repositories\Eloquent;

use App\Modules\Affiliates\Models\AffiliateSsoToken;
use App\Modules\Affiliates\Repositories\Contracts\SsoTokenRepositoryContract;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<AffiliateSsoToken>
 */
class SsoTokenRepository extends BaseRepository implements SsoTokenRepositoryContract
{
    protected function model(): string
    {
        return AffiliateSsoToken::class;
    }

    public function lockByHash(string $hash): ?AffiliateSsoToken
    {
        return AffiliateSsoToken::query()->where('token_hash', $hash)->lockForUpdate()->first();
    }

    public function prune(): int
    {
        return AffiliateSsoToken::query()->where('expires_at', '<', now()->subDay())->delete();
    }
}
