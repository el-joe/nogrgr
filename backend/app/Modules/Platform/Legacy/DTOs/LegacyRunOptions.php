<?php

namespace App\Modules\Platform\Legacy\DTOs;

use App\Modules\Platform\Legacy\Enums\LegacyStep;

/**
 * Options of one `legacy:migrate` run.
 */
final readonly class LegacyRunOptions
{
    /**
     * @param  list<LegacyStep>  $steps  empty = all
     * @param  list<string>  $tenants  old store ids (uuid / slug); empty = all
     */
    public function __construct(
        public array $steps = [],
        public array $tenants = [],
        public bool $dryRun = false,
        public bool $freshMapping = false,
        public bool $copyMedia = true,
        public ?string $reportPath = null,
    ) {}

    public function wants(LegacyStep $step): bool
    {
        return $this->steps === [] || in_array($step, $this->steps, true);
    }

    public function wantsTenant(string $legacyId): bool
    {
        return $this->tenants === [] || in_array($legacyId, $this->tenants, true);
    }
}
