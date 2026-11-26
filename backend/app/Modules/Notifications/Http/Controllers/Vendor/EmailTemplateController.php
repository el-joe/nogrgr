<?php

namespace App\Modules\Notifications\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\DataTables\VendorEmailTemplatesDataTable;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Http\Requests\Vendor\StoreEmailTemplateRequest;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Services\StoreEmailTemplateService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Vendor › Settings › Email templates (VEN-080 / 080a, Figma Vendor Panel 91:114835):
 * the e-mails this store's shoppers receive — platform default or the store's own
 * version per event; edit per language with live preview, reset to default, sync every
 * non-customised template from the platform. {event} = EmailTemplateEvent value.
 */
class EmailTemplateController extends Controller
{
    public function __construct(private readonly StoreEmailTemplateService $templates) {}

    public function index(Request $request): View
    {
        return view('notifications::vendor.email-templates.index', ['canManage' => $this->canManage($request)]);
    }

    public function data(Request $request, VendorEmailTemplatesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, $this->canManage($request));
    }

    public function edit(Request $request, string $event): View
    {
        $case = $this->event($event);

        return view('notifications::vendor.email-templates.edit', [
            'row' => $this->templates->row($case),
            'samples' => $this->templates->sampleVariables($case),
            'canManage' => $this->canManage($request),
            'locales' => EmailTemplate::LOCALES,
        ]);
    }

    public function update(StoreEmailTemplateRequest $request, string $event): JsonResponse
    {
        $case = $this->event($event);
        $this->templates->customize($case, $request->toData());

        return JsonResponder::success(__('notifications::vendor_templates.saved', ['name' => $case->label()]), redirect: vendor_route('settings.email-templates.edit', ['event' => $case->value]));
    }

    public function reset(string $event): JsonResponse
    {
        $case = $this->event($event);
        $this->templates->reset($case);

        return JsonResponder::success(__('notifications::vendor_templates.reset_done', ['name' => $case->label()]), redirect: vendor_route('settings.email-templates.edit', ['event' => $case->value]));
    }

    public function sync(): JsonResponse
    {
        $count = $this->templates->syncFromPlatform();

        return JsonResponder::success(trans_choice('notifications::vendor_templates.synced', $count, ['count' => $count]));
    }

    public function preview(Request $request, string $event): Response
    {
        $locale = in_array($request->query('locale'), EmailTemplate::LOCALES, true) ? (string) $request->query('locale') : app()->getLocale();
        $rendered = $this->templates->preview($this->event($event), $locale);

        abort_if($rendered === null, 404);

        return response($rendered->html)->header('Content-Security-Policy', "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'");
    }

    private function event(string $event): EmailTemplateEvent
    {
        $case = EmailTemplateEvent::tryFrom($event);
        abort_if($case === null || ! StoreEmailTemplateService::supports($case), 404);

        return $case;
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('settings.email-templates.manage');
    }
}
