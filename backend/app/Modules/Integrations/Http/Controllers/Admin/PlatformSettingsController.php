<?php

namespace App\Modules\Integrations\Http\Controllers\Admin;

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Http\Requests\Admin\UpdateIntegrationSectionRequest;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Admin › Settings › Platform (decision #69): business settings that used to be .env —
 * Billing (tax, due / grace days, seller block), Checkout & store API (reservation
 * minutes, rate limits) and Domains & DNS defaults (store edge IPv4). Same field-driven
 * form as the Integrations cards; values reach config() through IntegrationConfigOverlay.
 */
class PlatformSettingsController extends Controller
{
    public function __construct(private readonly IntegrationSettingsService $settings) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.platform.show', IntegrationSection::Billing);
    }

    public function show(IntegrationSection $section): View
    {
        return view('integrations::admin.integrations.section', [
            'section' => $section,
            'form' => $this->settings->form($section),
            'statuses' => [],
            'redirectUri' => null,
        ]);
    }

    public function update(UpdateIntegrationSectionRequest $request, IntegrationSection $section): JsonResponse
    {
        $this->settings->save($request->payload());

        return JsonResponder::success(__('integrations::admin.platform.saved', ['name' => $section->label()]), data: ['form' => $this->settings->form($section)]);
    }
}
