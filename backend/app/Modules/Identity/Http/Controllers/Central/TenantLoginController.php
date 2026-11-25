<?php

namespace App\Modules\Identity\Http\Controllers\Central;

use App\Modules\Content\Support\CentralSeo;
use App\Modules\Identity\DTOs\VendorCredentialsData;
use App\Modules\Identity\Enums\SocialProvider;
use App\Modules\Identity\Http\Requests\VendorLoginRequest;
use App\Modules\Identity\Services\VendorAuthService;
use App\Shared\Http\Controller;
use App\Shared\Routing\VendorPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Central-host store login (WEB-022, Figma 12:42268): {central}/login (alias /tenant/login). Resolves the user's stores through
 * the central vendor_staff_directory; one store → straight in, several → store picker.
 */
class TenantLoginController extends Controller
{
    public function __construct(
        private readonly VendorAuthService $auth,
        private readonly CentralSeo $seo,
    ) {}

    public function create(Request $request): View
    {
        $socialUrls = [];

        foreach (SocialProvider::cases() as $provider) {
            if ($provider->isEnabled()) {
                $socialUrls[$provider->value] = route('central.social.redirect', ['provider' => $provider->value]);
            }
        }

        return view('content::central.auth.login', [
            'seo' => $this->seo->make(__('identity::store_login.login_title'), __('identity::store_login.login_description'), noindex: true),
            'action' => route('central.tenant.login.store'),
            'store' => $request->query('store'),
            'forgotUrl' => route('central.password.request'),
            'registerUrl' => route('central.register'),
            'socialUrls' => $socialUrls,
        ]);
    }

    public function store(VendorLoginRequest $request): RedirectResponse
    {
        $stores = $this->auth->loginFromCentral(VendorCredentialsData::fromRequest($request), $request);

        if ($stores->count() === 1) {
            return redirect()->to(VendorPanel::centralUrl($stores->firstOrFail()->slug));
        }

        return redirect()->route('central.tenant.stores');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('central.tenant.login');
    }
}
