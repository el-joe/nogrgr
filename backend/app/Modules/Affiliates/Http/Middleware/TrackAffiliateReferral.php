<?php

namespace App\Modules\Affiliates\Http\Middleware;

use App\Modules\Affiliates\Services\ReferralTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Central website: a GET with `?ref=<code>` records the affiliate click and sets the
 * referral cookie. Tracking never breaks the page (failures are reported).
 */
class TrackAffiliateReferral
{
    public function __construct(private readonly ReferralTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $request->query->has(ReferralTracker::QUERY)) {
            return $response;
        }

        try {
            $click = $this->tracker->track($request);

            if ($click !== null) {
                $response->headers->setCookie($this->tracker->cookieFor($click));
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
