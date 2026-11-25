<?php

namespace App\Shared\Navigation\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Supplies sidebar badge counts ("badge" keys of config/navigation/<surface>.php) for one
 * panel surface. Modules register implementations with the container tag
 * PanelBadgeCounter::TAG; PanelShell merges them.
 */
interface PanelBadgeCounter
{
    public const TAG = 'panel.badge-counters';

    public function surface(): string;

    /**
     * @return array<string, int> badge key => count
     */
    public function counts(?Authenticatable $user): array;
}
