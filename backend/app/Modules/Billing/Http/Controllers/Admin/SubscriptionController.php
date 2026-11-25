<?php

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\DataTables\SubscriptionsDataTable;
use App\Modules\Billing\DTOs\AssignPlanData;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Exports\SubscriptionsCsvExport;
use App\Modules\Billing\Http\Requests\Admin\AssignPlanRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Billing\Services\SubscriptionMetricsService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Billing\Services\TenantLookupService;
use App\Shared\DataTables\DataTableFilters;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subscriptions (ADM-047): list with status tabs / filters, KPIs, CSV export, and the
 * admin actions assign plan, renew, cancel. Permissions: subscriptions.view / .export / .manage.
 */
class SubscriptionController extends Controller
{
    private const FILTERS = ['status', 'plan_id', 'gateway', 'search'];

    public function __construct(
        private readonly SubscriptionService $service,
        private readonly SubscriptionMetricsService $metrics,
        private readonly PlanRepositoryContract $plans,
    ) {}

    public function index(): View
    {
        return view('billing::admin.subscriptions.index', [
            'summary' => $this->metrics->summary(),
            'counts' => $this->metrics->countsByStatus(),
            'statuses' => SubscriptionStatus::options(),
            'plans' => $this->plans->options(),
            'activePlans' => $this->plans->options(activeOnly: true),
            'gateways' => PaymentGateway::options(),
            'terms' => BillingTerm::options(),
        ]);
    }

    public function data(Request $request, SubscriptionsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, self::FILTERS);
    }

    public function export(Request $request, SubscriptionsCsvExport $export): StreamedResponse
    {
        return $export->download(DataTableFilters::fromRequest($request, self::FILTERS));
    }

    public function tenants(Request $request, TenantLookupService $lookup): JsonResponse
    {
        return new JsonResponse($lookup->search($request->string('q')->toString(), $request->integer('page', 1)));
    }

    public function assign(AssignPlanRequest $request): JsonResponse
    {
        $data = AssignPlanData::fromRequest($request)->with([
            'mark_paid' => $request->boolean('mark_paid'),
            'admin_id' => $request->user('admin')?->getAuthIdentifier(),
        ]);

        $subscription = $this->service->assign($data);

        return JsonResponder::created(__('billing::subscriptions.assigned'), ['id' => $subscription->id], route('admin.billing.subscriptions.index'));
    }

    public function renew(Subscription $subscription): JsonResponse
    {
        $this->service->renew($subscription);

        return JsonResponder::success(__('billing::subscriptions.renewed'));
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $this->service->cancel($subscription);

        return JsonResponder::success(__('billing::subscriptions.cancelled'));
    }
}
