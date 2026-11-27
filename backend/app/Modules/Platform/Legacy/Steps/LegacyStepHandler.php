<?php

namespace App\Modules\Platform\Legacy\Steps;

use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Support\LegacyContext;

/**
 * One step of the old → new migration. Must be idempotent (all writes through
 * LegacyWriter::upsert / syncPivot) and read the old DB in chunks.
 */
interface LegacyStepHandler
{
    public function step(): LegacyStep;

    public function run(LegacyContext $context): void;
}
