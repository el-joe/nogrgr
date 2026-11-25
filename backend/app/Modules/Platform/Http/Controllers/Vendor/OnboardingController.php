<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Http\Requests\Vendor\UploadStoreLogoRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\OnboardingService;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Vendor onboarding (VEN-007): Tour and Setup checklist tabs, tour complete / dismiss,
 * payment-readiness skip and the logo upload of the checklist.
 */
class OnboardingController extends Controller
{
    public const TABS = ['checklist', 'tour'];

    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly StoreSettingsService $settings,
    ) {}

    public function show(Request $request): View
    {
        $tenant = $this->tenant();
        $progress = $this->onboarding->progress($tenant);
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : ($progress->tourFinished ? 'checklist' : 'tour');

        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return view('platform::vendor.onboarding.show', [
            'tenant' => $tenant,
            'staff' => $staff,
            'progress' => $progress,
            'tab' => $tab,
            'tour' => $this->onboarding->tourStops(),
            'logoUrl' => $this->settings->logoUrl(),
            'canManage' => $staff->can('onboarding.manage'),
            'canUploadLogo' => $staff->can('settings.store.manage'),
        ]);
    }

    public function completeTour(): RedirectResponse
    {
        $this->onboarding->completeTour();

        return redirect()->to(vendor_route('onboarding', ['tab' => 'checklist']))->with('success', __('platform::onboarding.tour.completed'));
    }

    public function dismissTour(): RedirectResponse
    {
        $this->onboarding->dismissTour();

        return redirect()->to(vendor_route('onboarding', ['tab' => 'checklist']))->with('info', __('platform::onboarding.tour.dismissed'));
    }

    public function skipPaymentReadiness(): RedirectResponse
    {
        $this->onboarding->skipPaymentReadiness();

        return redirect()->to(vendor_route('onboarding', ['tab' => 'checklist']))->with('info', __('platform::onboarding.payment.skipped'));
    }

    public function uploadLogo(UploadStoreLogoRequest $request): JsonResponse|RedirectResponse
    {
        $file = $request->file('logo');
        abort_unless($file instanceof UploadedFile, 422);

        $this->settings->replaceLogo($file);
        $redirect = vendor_route('onboarding', ['tab' => 'checklist']);

        return $request->expectsJson()
            ? JsonResponder::success(__('platform::onboarding.logo.saved'), ['url' => $this->settings->logoUrl()], $redirect)
            : redirect()->to($redirect)->with('success', __('platform::onboarding.logo.saved'));
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
