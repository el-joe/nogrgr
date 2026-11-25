<?php

namespace App\Modules\Platform\Http\Controllers\Central;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Platform\DTOs\ChoosePlanData;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Central\ChoosePlanRequest;
use App\Modules\Platform\Services\RegistrationFlowService;
use App\Modules\Platform\Support\RegistrationSession;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Plan step (WEB-016b, Figma 125:10149 / m 125:10421): monthly / yearly switch, plan
 * cards, free trial or pay now. Reached with a sign-up draft, or with the visitor's
 * unpaid registration ("Change plan" after a failed payment).
 */
class RegistrationPlanController extends Controller
{
    public function __construct(private readonly RegistrationFlowService $flow) {}

    public function show(Request $request): View|RedirectResponse
    {
        $session = new RegistrationSession($request->session());
        $current = $this->flow->current($session);
        $replanning = $current !== null && in_array($current->status, [RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed], true);

        if ($session->draft() === null && ! $replanning) {
            return redirect()->route('central.register');
        }

        $preselected = $session->preselectedPlan();
        $plans = $this->flow->plans();
        $term = $replanning ? ($current->term ?? BillingTerm::Monthly) : (BillingTerm::tryFrom((string) ($preselected['term'] ?? '')) ?? BillingTerm::Yearly);
        $selected = $replanning ? $current->plan_id : ($preselected['plan'] ?? null);

        return view('platform::central.signup.plan', [
            'plans' => $plans,
            'term' => $term,
            'selectedId' => $plans->contains('id', $selected) ? $selected : $plans->first()?->id,
            'quotes' => $plans->mapWithKeys(fn ($plan): array => [$plan->id => [
                'monthly' => $this->flow->quote($plan, BillingTerm::Monthly),
                'yearly' => $this->flow->quote($plan, BillingTerm::Yearly),
            ]]),
        ]);
    }

    public function store(ChoosePlanRequest $request): RedirectResponse
    {
        $session = new RegistrationSession($request->session());

        try {
            $registration = $this->flow->choosePlan($session, ChoosePlanData::fromRequest($request));
        } catch (PlatformRuleException $exception) {
            if ($session->draft() === null && $this->flow->current($session) === null) {
                return redirect()->route('central.register');
            }

            return back()->withInput()->with('error', $exception->getMessage());
        }

        return match ($registration->status) {
            RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed => redirect()->route('central.register.payment'),
            RegistrationStatus::Incomplete => redirect()->route('central.register.continue'),
            default => redirect()->route('central.register.verify'),
        };
    }
}
