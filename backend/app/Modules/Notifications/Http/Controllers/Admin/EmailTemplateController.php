<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Modules\Identity\Models\Admin;
use App\Modules\Notifications\DataTables\EmailTemplatesDataTable;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Http\Requests\Admin\EmailTemplateRequest;
use App\Modules\Notifications\Http\Requests\Admin\SendTestEmailTemplateRequest;
use App\Modules\Notifications\Http\Requests\RenderEmailTemplateRequest;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use App\Modules\Notifications\Services\EmailTemplateService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admin › Settings › Email templates (ADM-102 list, Figma Admin Panel 79:89188;
 * ADM-102a edit per locale, Figma 79:90124; decision #118): every registry e-mail with
 * enable / disable, subject + body per locale, variable picker, live server preview,
 * send test to the current admin, reset to default, last edited by / at and
 * "Sync to tenants" (one or all).
 */
class EmailTemplateController extends Controller
{
    public const PREVIEW_CSP = "default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'";

    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $this->templates->ensureRegistry();
        $tab = EmailAudience::tryFrom((string) $request->query('tab'))->value ?? 'all';

        return view('notifications::admin.email-templates.index', [
            'tab' => $tab,
            'counts' => $this->templates->counts(),
        ]);
    }

    public function data(Request $request, EmailTemplatesDataTable $table): JsonResponse
    {
        $this->templates->ensureRegistry();

        return $table->toJsonFromRequest($request, EmailTemplatesDataTable::FILTERS);
    }

    public function edit(EmailTemplate $template): View
    {
        return view('notifications::admin.email-templates.form', ['template' => $template]);
    }

    public function update(EmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        $this->templates->update($template, $request->payload(), $this->editor($request));

        return JsonResponder::success(__('notifications::templates.updated', ['name' => $template->name()]));
    }

    public function render(RenderEmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        $rendered = $this->templates->previewContent($template->event, $request->subjectText(), $request->bodyHtml(), $request->locale());

        return response()->json(['subject' => $rendered->subject, 'html' => $rendered->html]);
    }

    public function reset(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->templates->reset($template, $this->editor($request));

        return JsonResponder::success(__('notifications::templates.reset_done', ['name' => $template->name()]), redirect: route('admin.settings.email-templates.edit', $template));
    }

    public function preview(Request $request, EmailTemplate $template): Response
    {
        $locale = in_array($request->query('locale'), EmailTemplate::LOCALES, true) ? (string) $request->query('locale') : app()->getLocale();
        $rendered = $this->renderer->render($template, $locale, $template->event->variables());

        return response($rendered->html)->header('Content-Security-Policy', self::PREVIEW_CSP);
    }

    public function test(SendTestEmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        $email = (string) $request->user('admin')?->email;

        try {
            $this->templates->sendTest($template, (string) $request->validated('locale'), $email);
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__('notifications::templates.test_sent', ['email' => $email]));
    }

    public function sync(EmailTemplate $template): JsonResponse
    {
        $this->templates->syncToTenants($template);

        return JsonResponder::success(__('notifications::templates.sync_queued_one', ['name' => $template->name()]));
    }

    public function syncAll(): JsonResponse
    {
        $this->templates->syncToTenants();

        return JsonResponder::success(__('notifications::templates.sync_queued_all'));
    }

    private function editor(Request $request): ?string
    {
        $admin = $request->user('admin');

        return $admin instanceof Admin ? (string) $admin->name : null;
    }
}
