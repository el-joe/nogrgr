<?php

namespace App\Modules\Engagement\Http\Controllers\Storefront;

use App\Modules\Engagement\Http\Requests\Storefront\NewsletterSignupRequest;
use App\Modules\Engagement\Http\Requests\Storefront\StoreSupportRequest;
use App\Modules\Engagement\Services\NewsletterService;
use App\Modules\Engagement\Services\SupportRequestService;
use App\Modules\Identity\Models\Customer;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Storefront support form + footer newsletter signup (SF-ECO-035), JSON for the
 * success toast / inline errors.
 */
class SupportController extends Controller
{
    public function __construct(
        private readonly SupportRequestService $support,
        private readonly NewsletterService $newsletter,
    ) {}

    public function store(StoreSupportRequest $request): JsonResponse
    {
        $ticket = $this->support->submit($request->toData($this->customer()?->id));

        return JsonResponder::created(__st('support.sent', ['ticket' => $ticket->ticketNumber()]), ['ticket' => $ticket->ticketNumber()]);
    }

    public function newsletter(NewsletterSignupRequest $request): JsonResponse
    {
        $new = $this->newsletter->subscribe($request->email(), app()->getLocale(), $this->customer()?->id);

        return $new
            ? JsonResponder::created(__st('support.newsletter.subscribed'))
            : JsonResponder::success(__st('support.newsletter.already'));
    }

    private function customer(): ?Customer
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer ? $customer : null;
    }
}
