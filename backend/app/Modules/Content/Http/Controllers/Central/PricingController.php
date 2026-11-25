<?php

namespace App\Modules\Content\Http\Controllers\Central;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Content\Support\CentralSeo;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Plans / pricing (WEB-005, Figma 12:42011): active Billing plans with monthly / yearly
 * prices, limits and features; CTAs open the sign-up with the plan + term preselected.
 */
class PricingController extends Controller
{
    public function __construct(
        private readonly PlanRepositoryContract $plans,
        private readonly CentralSeo $seo,
    ) {}

    public function __invoke(Request $request): View
    {
        $term = BillingTerm::tryFrom((string) $request->query('term', '')) ?? BillingTerm::Monthly;

        return view('content::central.pricing', [
            'seo' => $this->seo->make(
                __('content::central.seo.pricing.title'),
                __('content::central.seo.pricing.description'),
                [['name' => __('content::central.seo.pricing.title'), 'url' => null]],
            ),
            'plans' => $this->plans->ordered()->filter(fn (Plan $plan): bool => $plan->status === PlanStatus::Active)->values(),
            'term' => $term->value,
        ]);
    }
}
