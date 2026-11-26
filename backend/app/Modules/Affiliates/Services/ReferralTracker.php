<?php

namespace App\Modules\Affiliates\Services;

use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\ClickRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * `?ref=<affiliate code>` on the central website: records one click per visitor and
 * affiliate (a repeat visit inside the cookie window reuses the open click) and keeps
 * the click id in the `nogrgr_ref` cookie for ProgramSettingsService::cookieDays().
 * Bots, prefetches and unknown / inactive codes are ignored.
 */
class ReferralTracker extends BaseService
{
    public const COOKIE = 'nogrgr_ref';

    public const QUERY = 'ref';

    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless/i';

    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly ClickRepositoryContract $clicks,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function track(Request $request): ?AffiliateClick
    {
        $code = $request->query(self::QUERY);
        $agent = (string) $request->userAgent();

        if (! is_string($code) || ! preg_match('/^[A-Za-z0-9_-]{3,32}$/', $code) || $agent === '' || preg_match(self::BOTS, $agent) === 1
            || in_array(Str::lower((string) ($request->header('Purpose') ?? $request->header('Sec-Purpose'))), ['prefetch', 'prerender'], true)) {
            return null;
        }

        $affiliate = $this->affiliates->findActiveByCode($code);

        if ($affiliate === null) {
            return null;
        }

        $current = $this->currentClick($request);

        if ($current !== null && $current->affiliate_id === $affiliate->id) {
            return $current;
        }

        /** @var AffiliateClick $click */
        $click = $this->clicks->create([
            'affiliate_id' => $affiliate->id,
            'ip' => $request->ip(),
            'user_agent' => Str::limit($agent, 500, ''),
            'landing_url' => Str::limit($request->fullUrl(), 1000, ''),
        ]);

        return $click;
    }

    public function cookieFor(AffiliateClick $click): Cookie
    {
        return cookie(self::COOKIE, (string) $click->id, $this->settings->cookieDays() * 1440, null, null, null, true, false, 'lax');
    }

    /**
     * The visitor's still-valid, unconverted click (cookie), if any.
     */
    public function currentClick(Request $request): ?AffiliateClick
    {
        $id = filter_var($request->cookie(self::COOKIE), FILTER_VALIDATE_INT);

        return $id === false ? null : $this->openClick($id);
    }

    public function currentClickId(Request $request): ?int
    {
        return $this->currentClick($request)?->id;
    }

    public function openClick(int $id): ?AffiliateClick
    {
        return $this->clicks->findOpen($id, now()->subDays($this->settings->cookieDays()));
    }
}
