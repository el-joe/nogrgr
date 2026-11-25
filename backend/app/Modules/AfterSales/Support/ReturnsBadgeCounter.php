<?php

namespace App\Modules\AfterSales\Support;

use App\Modules\AfterSales\Repositories\Contracts\ReturnRequestRepositoryContract;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Admin sidebar badge "returns": open return requests of every store.
 */
class ReturnsBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly ReturnRequestRepositoryContract $returns) {}

    public function surface(): string
    {
        return 'admin';
    }

    public function counts(?Authenticatable $user): array
    {
        if (! $user instanceof Authorizable || ! $user->can('returns.view')) {
            return [];
        }

        return ['returns' => $this->returns->openCount()];
    }
}
