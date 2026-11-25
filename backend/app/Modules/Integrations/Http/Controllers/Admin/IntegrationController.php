<?php

namespace App\Modules\Integrations\Http\Controllers\Admin;

use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Http\Requests\Admin\TestIntegrationRequest;
use App\Modules\Integrations\Http\Requests\Admin\UpdateIntegrationSectionRequest;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use App\Modules\Integrations\Support\SocialRedirectUri;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Admin › Integrations (ADM-100, Figma Admin Panel 51:17971 / 53:18783 / 79:87472):
 * tab shell; Mail, Google login, Apple login, Reverb/Pusher, Cloudflare/DNS and AI
 * translation cards. Each card saves independently and has its own test action.
 */
class IntegrationController extends Controller
{
    public function __construct(private readonly IntegrationSettingsService $settings) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.integrations.gateways.index');
    }

    public function show(IntegrationSection $section): View
    {
        $targets = $section->testTargets();
        $statuses = [];

        foreach ($targets === [] ? [null] : $targets as $target) {
            $statuses[(string) $target] = [
                'status' => $this->settings->status($section, $target === null ? null : (string) $target),
                'last' => $this->settings->lastTest($section, $target === null ? null : (string) $target),
            ];
        }

        return view('integrations::admin.integrations.section', [
            'section' => $section,
            'form' => $this->settings->form($section),
            'statuses' => $statuses,
            'redirectUri' => in_array($section, [IntegrationSection::Google, IntegrationSection::Apple], true) ? SocialRedirectUri::for($section->value) : null,
        ]);
    }

    public function update(UpdateIntegrationSectionRequest $request, IntegrationSection $section): JsonResponse
    {
        $this->settings->save($request->payload());

        return JsonResponder::success(__('integrations::admin.saved', ['name' => $section->label()]), data: ['form' => $this->settings->form($section)]);
    }

    public function test(TestIntegrationRequest $request, IntegrationSection $section): JsonResponse
    {
        $result = $this->settings->test($section, $request->target());
        $data = ['status' => $result->status()->value, 'status_label' => $result->status()->label(), 'error' => $result->error?->value, 'detail' => $result->detail];

        if (! $result->ok) {
            return JsonResponder::error($result->message(), 422, $data);
        }

        $message = $section === IntegrationSection::Mail
            ? __('integrations::admin.mail.test_sent', ['email' => (string) $request->target()])
            : $result->message();

        return JsonResponder::success($message, $data);
    }
}
