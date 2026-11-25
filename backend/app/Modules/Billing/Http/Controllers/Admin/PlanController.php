<?php

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\DataTables\PlansDataTable;
use App\Modules\Billing\DTOs\PlanData;
use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Billing\Http\Requests\Admin\PlanRequest;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Services\PlanService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plans (ADM-046 list, ADM-046a create / edit). Permissions: plans.view / plans.manage.
 */
class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $service,
        private readonly PlanRepositoryContract $plans,
    ) {}

    public function index(): View
    {
        return view('billing::admin.plans.index', [
            'plans' => $this->plans->ordered(),
            'limits' => PlanLimit::cases(),
            'features' => PlanFeature::cases(),
            'statuses' => PlanStatus::options(),
        ]);
    }

    public function data(Request $request, PlansDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'search']);
    }

    public function create(): View
    {
        return $this->form(new Plan(['status' => PlanStatus::Active, 'trial_days' => 0, 'sort_order' => 0]));
    }

    public function store(PlanRequest $request): JsonResponse
    {
        $plan = $this->service->create(PlanData::fromPlanRequest($request));

        return JsonResponder::created(__('billing::plans.created'), ['id' => $plan->id], route('admin.plans.index'));
    }

    public function edit(Plan $plan): View
    {
        return $this->form($plan);
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse
    {
        $this->service->update($plan, PlanData::fromPlanRequest($request));

        return JsonResponder::success(__('billing::plans.updated'), null, route('admin.plans.index'));
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->service->delete($plan);

        return JsonResponder::success(__('billing::plans.deleted'), null, route('admin.plans.index'));
    }

    private function form(Plan $plan): View
    {
        return view('billing::admin.plans.form', [
            'plan' => $plan,
            'limits' => PlanLimit::cases(),
            'features' => PlanFeature::cases(),
            'statuses' => PlanStatus::options(),
        ]);
    }
}
