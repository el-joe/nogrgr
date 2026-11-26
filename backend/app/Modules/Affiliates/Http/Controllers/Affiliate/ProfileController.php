<?php

namespace App\Modules\Affiliates\Http\Controllers\Affiliate;

use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Http\Controllers\Concerns\ResolvesSignedInAffiliate;
use App\Modules\Affiliates\Http\Requests\Portal\ChangePasswordRequest;
use App\Modules\Affiliates\Http\Requests\Portal\UpdatePayoutDetailsRequest;
use App\Modules\Affiliates\Http\Requests\Portal\UpdateProfileRequest;
use App\Modules\Affiliates\Services\AffiliateProfileService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Support\Locale;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Affiliate › Profile (AFF-013): tabs profile / payout details (AFF-013a) / password
 * (AFF-013b). Each tab saves through its own AJAX form.
 */
class ProfileController extends Controller
{
    use ResolvesSignedInAffiliate;

    public function __construct(private readonly AffiliateProfileService $profiles) {}

    public function edit(Request $request): View
    {
        $locales = [];

        foreach (Locale::enabled() as $code => $language) {
            $locales[$code] = $language['native'];
        }

        return view('affiliates::portal.profile', [
            'affiliate' => $this->affiliate($request),
            'locales' => $locales,
            'methods' => [
                PayoutMethod::Paypal->value => PayoutMethod::Paypal->label(),
                PayoutMethod::BankTransfer->value => PayoutMethod::BankTransfer->label(),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $data = $request->toData();
        $this->profiles->update($this->affiliate($request), $data);

        return JsonResponder::success(__('affiliates::panel.profile.saved'), redirect: $data->locale !== app()->getLocale() ? route('affiliate.profile.edit', ['lang' => $data->locale]) : null);
    }

    public function payout(UpdatePayoutDetailsRequest $request): JsonResponse
    {
        $this->profiles->updatePayoutDetails($this->affiliate($request), $request->toData());

        return JsonResponder::success(__('affiliates::panel.profile.payout_saved'));
    }

    public function password(ChangePasswordRequest $request): JsonResponse
    {
        $this->profiles->changePassword($this->affiliate($request), $request->password());

        return JsonResponder::success(__('affiliates::panel.profile.password_saved'));
    }
}
