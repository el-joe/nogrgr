<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Catalog\Enums\TenantChangeType;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Http\Requests\Vendor\StoreChangeRequestRequest;
use App\Modules\Platform\Http\Requests\Vendor\UpdateGeneralSettingsRequest;
use App\Modules\Platform\Services\StoreGeneralSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › General (VEN-071, Figma Vendor Panel 90:112042): Store card (name,
 * support e-mail, default profit %, timezone) and the Countries / Categories cards with
 * their change request modals (VEN-071a / 071b, 91:119876) and pending state.
 * Permissions: settings.general.view (page) / settings.general.manage (save, requests).
 */
class GeneralSettingsController extends Controller
{
    public function __construct(private readonly StoreGeneralSettingsService $general) {}

    public function index(Request $request): View
    {
        $staff = $request->user('vendor');
        $cards = [];

        foreach (TenantChangeType::cases() as $type) {
            $pending = $this->general->pending($type);
            $cards[$type->value] = [
                'current' => $this->general->currentNames($type),
                'currentIds' => $this->general->currentIds($type),
                'addOptions' => $this->general->addOptions($type),
                'pending' => $pending,
                'pendingSummary' => $pending !== null ? $this->general->pendingSummary($pending) : null,
            ];
        }

        $timezones = [];

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $timezones[$timezone] = str_replace('_', ' ', $timezone);
        }

        return view('platform::vendor.settings.general', [
            'settings' => $this->general->current(),
            'cards' => $cards,
            'timezones' => $timezones,
            'canManage' => $staff instanceof VendorStaff && $staff->can('settings.general.manage'),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        $this->general->save($request->toData());

        return JsonResponder::success(__('platform::vendor_general.saved'), redirect: vendor_route('settings.general'));
    }

    public function requestChange(StoreChangeRequestRequest $request): JsonResponse
    {
        $this->general->requestChange($request->toData());

        return JsonResponder::success(__('platform::vendor_general.requested'), redirect: vendor_route('settings.general'));
    }
}
