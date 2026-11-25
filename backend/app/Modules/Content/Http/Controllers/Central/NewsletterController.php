<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Content\Http\Requests\Central\NewsletterRequest;
use App\Modules\Content\Services\NewsletterService;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Footer newsletter (WEB-015): double opt-in signup, confirm link, one-click unsubscribe.
 */
class NewsletterController extends Controller
{
    public function __construct(
        private readonly NewsletterService $newsletter,
        private readonly CentralSeo $seo,
    ) {}

    public function store(NewsletterRequest $request): JsonResponse
    {
        if ($request->isSpam()) {
            return JsonResponder::success(__('content::central.newsletter.pending'));
        }

        $result = $this->newsletter->subscribe((string) $request->validated('email'), app()->getLocale(), $request->ip());

        return JsonResponder::success(__('content::central.newsletter.'.$result));
    }

    public function confirm(string $token): Response
    {
        $subscriber = $this->newsletter->confirm($token);

        return $this->result($subscriber !== null ? 'confirmed' : 'invalid', $subscriber !== null ? 200 : 404);
    }

    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->newsletter->unsubscribe($token);

        return $this->result($subscriber !== null ? 'unsubscribed' : 'invalid', $subscriber !== null ? 200 : 404);
    }

    private function result(string $state, int $status): Response
    {
        return response()->view('content::central.newsletter.result', [
            'seo' => $this->seo->make(__('content::central.newsletter.'.$state.'_title'), __('content::central.newsletter.'.$state.'_text'), noindex: true),
            'state' => $state,
        ], $status);
    }
}
