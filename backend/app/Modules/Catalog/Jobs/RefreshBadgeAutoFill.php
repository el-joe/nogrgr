<?php

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Enums\BadgeType;
use App\Modules\Catalog\Services\BadgeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refills automatic badge lists (newest / top selling). One badge when a type is given
 * (after its settings are saved), every badge otherwise (daily schedule).
 */
class RefreshBadgeAutoFill implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public readonly ?BadgeType $type = null) {}

    public function uniqueId(): string
    {
        return $this->type->value ?? 'all';
    }

    public function handle(BadgeService $badges): void
    {
        $this->type === null
            ? $badges->refreshAllAutoFills()
            : $badges->refreshAutoFill($badges->badge($this->type));
    }
}
