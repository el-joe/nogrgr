<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Modules\Notifications\DataTables\EmailTemplatesDataTable;
use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Http\Requests\Admin\EmailTemplateRequest;
use App\Modules\Notifications\Http\Requests\Admin\SendTestEmailTemplateRequest;
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
 * ADM-102a create / edit per locale with live preview, Figma 79:90124): send test,
 * preview, delete and "Sync to tenants" (one or all).
 */
class EmailTemplateController extends Controller
{
    public function __construct(
        private readonly EmailTemplateService $templates,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function index(Request $request): View
    {
        $tab = EmailAudience::tryFrom((string) $request->query('tab'))?->value ?? 'all';

        return view('notifications::admin.email-templates.index', [
            'tab' => $tab,
            'counts' => $this->templates->counts(),
            'canCreate' => $this->templates->availableEvents() !== [],
        ]);
    }

    public function data(Request $request, EmailTemplatesDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, EmailTemplatesDataTable::FILTERS);
    }

    public function create(Request $request): View
    {
        $events = $this->templates->availableEvents();
        $selected = EmailTemplateEvent::tryFrom((string) $request->query('event'));

        return view('notifications::admin.email-templates.form', [
            'template' => null,
            'events' => $events,
            'event' => $selected ?? EmailTemplateEvent::tryFrom((string) array_key_first((array) reset($events) ?: [])),
        ]);
    }

    public function store(EmailTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templates->create($request->payload());
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::created(__('notifications::templates.created', ['name' => $template->name()]), redirect: route('admin.settings.email-templates.edit', $template));
    }

    public function edit(EmailTemplate $template): View
    {
        return view('notifications::admin.email-templates.form', [
            'template' => $template,
            'events' => [],
            'event' => $template->event,
        ]);
    }

    public function update(EmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        $this->templates->update($template, $request->payload());

        return JsonResponder::success(__('notifications::templates.updated', ['name' => $template->name()]));
    }

    public function destroy(EmailTemplate $template): JsonResponse
    {
        $this->templates->delete($template);

        return JsonResponder::success(__('notifications::templates.deleted', ['name' => $template->name()]));
    }

    public function preview(Request $request, EmailTemplate $template): Response
    {
        $locale = in_array($request->query('locale'), EmailTemplate::LOCALES, true) ? (string) $request->query('locale') : app()->getLocale();
        $rendered = $this->renderer->render($template, $locale, $template->event->variables());

        return response($rendered->html)->header('Content-Security-Policy', "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'");
    }

    public function test(SendTestEmailTemplateRequest $request, EmailTemplate $template): JsonResponse
    {
        try {
            $this->templates->sendTest($template, (string) $request->validated('locale'), (string) $request->validated('email'));
        } catch (DomainException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__('notifications::templates.test_sent', ['email' => (string) $request->validated('email')]));
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
}
