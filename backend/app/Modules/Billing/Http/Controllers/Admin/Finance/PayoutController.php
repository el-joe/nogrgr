<?php

namespace App\Modules\Billing\Http\Controllers\Admin\Finance;

use App\Modules\Billing\DataTables\Finance\PayoutsDataTable;
use App\Modules\Billing\DTOs\Finance\FinanceActor;
use App\Modules\Billing\Enums\PayoutMethod;
use App\Modules\Billing\Enums\PayoutStatus;
use App\Modules\Billing\Http\Requests\Finance\StorePayoutRequest;
use App\Modules\Billing\Http\Requests\Finance\StoreSearchRequest;
use App\Modules\Billing\Http\Requests\Finance\UpdatePayoutRequest;
use App\Modules\Billing\Repositories\Contracts\TenantPayoutRepositoryContract;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Billing\Services\Finance\PayoutService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Finance › Payouts platform → store (ADM-052 / 052a / 052b, Figma 74:61743 ·
 * 74:62688 · 74:68877): status tabs, filters, create payout for a store (available
 * balance, bank details on file, e-mail option), payout detail, edit (status, reference)
 * and cancel.
 */
class PayoutController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FinancePageService $pages,
        private readonly PayoutService $payouts,
        private readonly TenantPayoutRepositoryContract $repository,
    ) {}

    public function index(Request $request): View
    {
        $status = PayoutStatus::tryFrom((string) $request->query('status'));

        return view('billing::admin.finance.payouts.index', [
            'stats' => $this->repository->stats(),
            'active' => $status->value ?? 'all',
            'methods' => PayoutMethod::options(),
        ]);
    }

    public function data(Request $request, PayoutsDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->forTenant(null, 'admin', $admin->can('finance.payouts.manage'))->toJsonFromRequest($request, PayoutsDataTable::FILTERS);
    }

    public function stores(StoreSearchRequest $request): JsonResponse
    {
        return response()->json($this->pages->storeSearch($request->term(), $request->page()));
    }

    public function create(Request $request): View
    {
        $tenantId = filter_var($request->query('tenant_id'), FILTER_VALIDATE_INT);
        $tenant = $tenantId !== false ? $this->pages->tenant($tenantId) : null;

        return view('billing::admin.finance.payouts.create', [
            'tenant' => $tenant,
            'summary' => $tenant !== null ? $this->pages->summary($tenant->id) : null,
            'methods' => PayoutMethod::options(),
        ]);
    }

    public function store(StorePayoutRequest $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $payout = $this->payouts->create($request->toData(), new FinanceActor($admin->id, $admin->name));

        return JsonResponder::created((string) __('billing::finance.payouts.created', ['reference' => (string) $payout->reference]), ['id' => $payout->id], route('admin.finance.payouts.index'));
    }

    public function show(Request $request, int $payout): View
    {
        $row = $this->pages->payout($payout);
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $this->remoteModal($request, 'billing::finance._payout', [
            'payout' => $row,
            'editUrl' => $admin->can('finance.payouts.manage') && $row->status !== PayoutStatus::Cancelled ? route('admin.finance.payouts.edit', $row->id) : null,
        ], $this->page((string) $row->reference));
    }

    public function edit(Request $request, int $payout): View
    {
        $row = $this->pages->payout($payout);

        return $this->remoteModal($request, 'billing::admin.finance.payouts._edit-form', [
            'payout' => $row,
            'statuses' => $this->statusOptions($row->status),
            'formAction' => route('admin.finance.payouts.update', $row->id),
        ], $this->page((string) $row->reference));
    }

    public function update(UpdatePayoutRequest $request, int $payout): JsonResponse
    {
        $row = $this->payouts->update($this->pages->payout($payout), $request->toData());

        return JsonResponder::success((string) __('billing::finance.payouts.updated', ['reference' => (string) $row->reference]));
    }

    public function cancel(int $payout): JsonResponse
    {
        $row = $this->payouts->cancel($this->pages->payout($payout));

        return JsonResponder::success((string) __('billing::finance.payouts.cancelled', ['reference' => (string) $row->reference]));
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(PayoutStatus $current): array
    {
        $options = [$current->value => $current->label()];

        foreach ($current->transitions() as $next) {
            $options[$next->value] = $next->label();
        }

        return $options;
    }

    private function page(string $reference): RemoteModalPage
    {
        return new RemoteModalPage('admin', $reference, route('admin.finance.payouts.index'), 'admin-finance', null, null, [
            __('navigation.admin.finance') => null,
            __('navigation.admin.payouts') => route('admin.finance.payouts.index'),
            $reference => null,
        ]);
    }
}
