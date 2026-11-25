<?php

namespace App\Modules\Integrations\Http\Controllers\Admin;

use App\Modules\Integrations\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use App\Modules\Integrations\Services\GeneralSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DateTimeZone;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Admin › Settings › General (ADM-101, Figma Admin Panel 79:88365).
 */
class GeneralSettingsController extends Controller
{
    public function __construct(private readonly GeneralSettingsService $settings) {}

    public function edit(): View
    {
        $timezones = [];

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $timezones[$timezone] = str_replace('_', ' ', $timezone);
        }

        return view('integrations::admin.settings.general', [
            'settings' => $this->settings->current(),
            'timezones' => $timezones,
            'languages' => $this->settings->languageOptions(),
        ]);
    }

    public function update(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        try {
            $this->settings->save($request->payload());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__('integrations::general.saved'), redirect: route('admin.settings.general'));
    }
}
