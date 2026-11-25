<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Platform\Services\OwnerSupportService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;

/**
 * Owner portal › Help & support (OWN-009): how to reach the platform team (e-mail,
 * phone, social channels) from Admin › Settings › General, or an empty state.
 */
class OwnerSupportController extends Controller
{
    public function __construct(private readonly OwnerSupportService $support) {}

    public function __invoke(): View
    {
        return view('platform::owner.support', $this->support->channels());
    }
}
