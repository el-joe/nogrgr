<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An external catalog import run reached completed, failed or cancelled (after commit).
 * Listener: NotifyCatalogImportFinished (admin who started it).
 */
final class CatalogImportFinished
{
    use Dispatchable;

    public function __construct(public readonly int $runId) {}
}
