<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\VendorAuthService;
use App\Modules\Platform\Http\Requests\Owner\SelectOwnedStoreRequest;
use App\Modules\Platform\Services\OwnerPortalService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Select store (OWN-002, Figma Affiliate & Owner 122:105321): after a sign-in that
 * matched several stores the owner picks the panel to open, or goes to My stores.
 * Stores that cannot be opened (suspended, not launched) are listed but disabled.
 */
class OwnerSelectStoreController extends Controller
{
    public function __construct(
        private readonly OwnerPortalService $portal,
        private readonly VendorAuthService $auth,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');
        $openable = $this->auth->pendingStores($request)->pluck('id')->all();
        $cards = $this->portal->cards($owner);

        if ($cards === []) {
            return redirect()->route('owner.stores.create');
        }

        return view('platform::owner.select-store', [
            'cards' => $cards,
            'openable' => $openable,
        ]);
    }

    public function store(SelectOwnedStoreRequest $request): RedirectResponse
    {
        $tenant = $this->auth->selectStore($request->integer('tenant_id'), $request);

        return redirect()->to(VendorPanel::centralUrl($tenant->slug));
    }
}
