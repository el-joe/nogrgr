<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\VendorAuthService;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\OwnerPortalService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Owner portal › My stores (OWN-001, Figma Affiliate & Owner 122:102288): one card per
 * store (state, plan, renewal / trial, unpaid invoice) and "Open store panel", which
 * signs the owner into that store's vendor panel as its owner staff account (SSO).
 */
class OwnerDashboardController extends Controller
{
    public function __construct(
        private readonly OwnerPortalService $portal,
        private readonly VendorAuthService $auth,
    ) {}

    public function index(Request $request): View
    {
        $cards = $this->portal->cards($this->owner($request));

        return view('platform::owner.dashboard', [
            'cards' => $cards,
            'summary' => $this->portal->summary($cards),
        ]);
    }

    public function open(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($this->owner($request)->can('owner.stores.manage', $tenant), 403);

        try {
            $this->auth->openOwnedStore($tenant->id, $request);
        } catch (ValidationException) {
            return redirect()->route('owner.dashboard')->with('error', __('platform::owner.dashboard.open_failed', ['store' => $tenant->name]));
        }

        return redirect()->to(VendorPanel::centralUrl($tenant->slug));
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
