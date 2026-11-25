<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Central\StoreAddressCheck;
use App\Modules\Platform\Http\Requests\Owner\AddStoreRequest;
use App\Modules\Platform\Http\Requests\Owner\StoreAddressAvailabilityRequest;
use App\Modules\Platform\Services\RegistrationFlowService;
use App\Modules\Platform\Support\RegistrationSession;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Add another store (OWN-007, Figma Affiliate & Owner 122:104860): same owner account,
 * separate store and subscription. Store name / address / country are taken here; the
 * signed-in owner then continues in the standard plan (WEB-016b) and payment (WEB-016c)
 * steps with the e-mail already proven, so no verification e-mail is sent.
 */
class AddStoreController extends Controller
{
    public function __construct(private readonly RegistrationFlowService $flow) {}

    public function create(CountryService $countries): View
    {
        return view('platform::owner.stores.create', [
            'countries' => $countries->options(),
            'baseDomain' => (string) config('tenancy.base_domain'),
            'subdomainExample' => TenantSlug::subdomainHost('store'),
        ]);
    }

    /**
     * Live store-address check: {available, message}.
     */
    public function availability(StoreAddressAvailabilityRequest $request, StoreAddressCheck $check): JsonResponse
    {
        $slug = (string) $request->validated('slug');
        $error = $check->slugError($slug, null);

        return response()->json([
            'available' => $error === null,
            'message' => $error ?? __('platform::register.store.available', ['host' => TenantSlug::subdomainHost($slug)]),
        ]);
    }

    public function store(AddStoreRequest $request): RedirectResponse
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');
        $session = new RegistrationSession($request->session());

        try {
            $draft = $this->flow->draftForOwner(
                $owner,
                (string) $request->validated('store_name'),
                (string) $request->validated('slug'),
                $request->validated('default_country_id') !== null ? (int) $request->validated('default_country_id') : null,
                app()->getLocale(),
            );
        } catch (PlatformRuleException $exception) {
            return back()->withInput()->withErrors(['slug' => $exception->getMessage()]);
        }

        $session->forget();
        $this->flow->start($session, $draft);

        return redirect()->route('central.register.plan');
    }
}
