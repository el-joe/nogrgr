<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Models\Requests\EditRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A product edit request was approved (overrides written) or rejected.
 */
final class EditRequestReviewed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly EditRequest $editRequest) {}
}
