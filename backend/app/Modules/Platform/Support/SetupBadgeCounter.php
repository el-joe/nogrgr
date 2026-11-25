<?php

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\OnboardingService;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendor sidebar "setup" badge: setup checklist steps still open.
 */
final class SetupBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function surface(): string
    {
        return 'vendor';
    }

    public function counts(?Authenticatable $user): array
    {
        $tenant = tenant();

        if ($user === null || ! $tenant instanceof Tenant) {
            return [];
        }

        return ['setup' => $this->onboarding->progress($tenant)->remaining()];
    }
}
