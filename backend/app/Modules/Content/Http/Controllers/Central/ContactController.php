<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\DTOs\ContactMessageData;
use App\Modules\Content\Http\Requests\Central\ContactRequest;
use App\Modules\Content\Services\CentralSiteService;
use App\Modules\Content\Services\ContactService;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * Contact (WEB-008, Figma 12:41842): channels from General settings and the form
 * (stored, admins notified + support inbox mailed). Honeypot hits get the same success.
 */
class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contact,
        private readonly CentralSiteService $site,
        private readonly CentralSeo $seo,
    ) {}

    public function show(): View
    {
        return view('content::central.contact', [
            'seo' => $this->seo->make(
                __('content::central.seo.contact.title'),
                __('content::central.seo.contact.description'),
                [['name' => __('content::central.seo.contact.title'), 'url' => null]],
            ),
            'channels' => $this->site->channels(),
        ]);
    }

    public function store(ContactRequest $request): JsonResponse
    {
        if (! $request->isSpam()) {
            $this->contact->submit(ContactMessageData::fromRequest($request), $request->ip(), $request->userAgent());
        }

        return JsonResponder::success(__('content::central.contact.sent'));
    }
}
