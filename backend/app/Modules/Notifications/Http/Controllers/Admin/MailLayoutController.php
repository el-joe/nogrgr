<?php

namespace App\Modules\Notifications\Http\Controllers\Admin;

use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Http\Requests\Admin\UpdateMailLayoutRequest;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use App\Modules\Notifications\Services\MailLayoutService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admin › Settings › Email layout (MAIL-001, decision #118): the shared branded layout
 * of every platform e-mail (logo, brand / background colour, footer per locale, address,
 * help / privacy links, social links) with an EN / AR preview. Store e-mails use the
 * store's logo, colour and social links with this footer.
 */
class MailLayoutController extends Controller
{
    public function __construct(
        private readonly MailLayoutService $layout,
        private readonly EmailTemplateRenderer $renderer,
    ) {}

    public function edit(Request $request): View
    {
        return view('notifications::admin.email-layout', [
            'settings' => $this->layout->current(),
            'canManage' => (bool) $request->user('admin')?->can('email-templates.manage'),
        ]);
    }

    public function update(UpdateMailLayoutRequest $request): JsonResponse
    {
        $this->layout->save($request->toData());

        return JsonResponder::success(__('notifications::layout.saved'), redirect: route('admin.settings.email-layout.edit'));
    }

    public function preview(Request $request): Response
    {
        $locale = in_array($request->query('locale'), EmailTemplate::LOCALES, true) ? (string) $request->query('locale') : app()->getLocale();
        $event = EmailTemplateEvent::VendorWelcome;
        $rendered = $this->renderer->renderDefault($event, $locale, $event->variables());

        return response($rendered->html)->header('Content-Security-Policy', EmailTemplateController::PREVIEW_CSP);
    }
}
