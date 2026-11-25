<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Models\Requests\TenantChangeRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store change request was approved (tenant updated) or rejected.
 */
final class TenantChangeReviewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly TenantChangeRequest $changeRequest) {}
}
