<?php

namespace App\Modules\Affiliates\Http\Controllers\Admin;

use App\Modules\Affiliates\Http\Requests\ProgramSettingsRequest;
use App\Modules\Affiliates\Services\ProgramSettingsService;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ADM-060c "Affiliate program settings" modal (Figma 74:68877): cookie days, default
 * commission, minimum payout — saved in the DB business section `affiliates`.
 */
class ProgramSettingsController extends Controller
{
    use RendersRemoteModals;

    public function __construct(private readonly ProgramSettingsService $settings) {}

    public function edit(Request $request): View
    {
        return $this->remoteModal($request, 'affiliates::admin.accounts._settings-form', [
            'cookieDays' => $this->settings->cookieDays(),
            'defaultCommission' => $this->settings->defaultCommissionBps() / 100,
            'minPayout' => $this->settings->minPayout(),
        ], new RemoteModalPage('admin', (string) __('affiliates::admin.settings.title'), route('admin.affiliates.index'), 'admin-affiliates', null, null, [
            __('navigation.admin.affiliates') => null,
            __('navigation.admin.program_settings') => null,
        ]));
    }

    public function update(ProgramSettingsRequest $request): JsonResponse
    {
        $this->settings->save($request->toData());

        return JsonResponder::success((string) __('affiliates::admin.settings.saved'));
    }
}
