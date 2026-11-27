<?php

namespace App\Modules\Notifications\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Inventory\Services\Alerts\StockAlertService;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Modules\Notifications\Http\Requests\Vendor\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Services\VendorNotificationPreferenceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Notifications (VEN-087, Figma Vendor Panel new screens 91:119215):
 * the signed-in member's own event × channel switches (in-app, e-mail, push), plus the
 * store-wide low-stock threshold card (catalog.products.manage).
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private readonly VendorNotificationPreferenceService $preferences,
        private readonly StockAlertService $stockAlerts,
    ) {}

    public function edit(Request $request): View
    {
        return view('notifications::vendor.preferences', [
            'topics' => VendorNotificationTopic::cases(),
            'channels' => NotificationChannel::cases(),
            'matrix' => $this->preferences->matrix($this->staff($request)),
            'stockThreshold' => $this->stockAlerts->storeThreshold(),
            'canManageStock' => $this->staff($request)->can('catalog.products.manage'),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $matrix = $this->preferences->update($this->staff($request), $request->toData());

        return JsonResponder::success(__('notifications::panel.preferences.saved'), ['preferences' => $matrix]);
    }

    private function staff(Request $request): VendorStaff
    {
        /** @var VendorStaff $staff */
        $staff = $request->user('vendor');

        return $staff;
    }
}
