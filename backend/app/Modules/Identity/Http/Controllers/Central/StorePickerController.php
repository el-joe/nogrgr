<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Identity\Http\Requests\SelectStoreRequest;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Services\VendorAuthService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Store picker after a central-host login that matched several stores.
 */
class StorePickerController extends Controller
{
    public function __construct(private readonly VendorAuthService $auth) {}

    public function index(Request $request): View|RedirectResponse
    {
        // A signed-in store owner picks from the owner portal (OWN-002).
        $owner = $request->user('owner');

        if ($owner instanceof Owner && $owner->isActive() && Route::has('owner.select-store')) {
            return redirect()->route('owner.select-store');
        }

        $stores = $this->auth->pendingStores($request);

        if ($stores->isEmpty()) {
            return redirect()->route('central.tenant.login');
        }

        return view('identity::tenant.stores', ['stores' => $stores]);
    }

    public function store(SelectStoreRequest $request): RedirectResponse
    {
        $tenant = $this->auth->selectStore($request->integer('tenant_id'), $request);

        return redirect()->to(VendorPanel::centralUrl($tenant->slug));
    }
}
