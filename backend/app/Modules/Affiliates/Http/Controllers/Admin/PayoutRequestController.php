<?php

namespace App\Modules\Affiliates\Http\Controllers\Admin;

use App\Modules\Affiliates\DataTables\PayoutRequestsDataTable;
use App\Modules\Affiliates\DataTables\PayoutsDataTable;
use App\Modules\Affiliates\DTOs\AdminActor;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Affiliates\Http\Requests\NoteRequest;
use App\Modules\Affiliates\Http\Requests\PayoutFormRequest;
use App\Modules\Affiliates\Models\AffiliatePayout;
use App\Modules\Affiliates\Models\AffiliatePayoutRequest;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRepositoryContract;
use App\Modules\Affiliates\Repositories\Contracts\PayoutRequestRepositoryContract;
use App\Modules\Affiliates\Services\PayoutService;
use App\Modules\Affiliates\Services\ProgramSettingsService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Affiliates › Payouts (ADM-062, Figma 74:67899 · 74:68877): payout requests by
 * status with the "Pay out" detail modal (balance + minimum, amount, reference, proof;
 * Reject / Mark paid), and the paid payout history (proof download).
 */
class PayoutRequestController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly PayoutRequestRepositoryContract $requests,
        private readonly PayoutRepositoryContract $payouts,
        private readonly PayoutService $service,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $status = PayoutRequestStatus::tryFrom((string) $request->query('status'));

        return view('affiliates::admin.payouts.index', [
            'counts' => $this->requests->counts(),
            'active' => $status->value ?? 'all',
        ]);
    }

    public function history(): View
    {
        return view('affiliates::admin.payouts.history', [
            'counts' => $this->requests->counts(),
            'methods' => PayoutMethod::options(),
        ]);
    }

    public function data(Request $request, PayoutRequestsDataTable $requests, PayoutsDataTable $payouts): JsonResponse
    {
        return $request->boolean('history')
            ? $payouts->toJsonFromRequest($request, PayoutsDataTable::FILTERS)
            : $requests->toJsonFromRequest($request, PayoutRequestsDataTable::FILTERS);
    }

    public function show(Request $request, int $payoutRequest): View
    {
        $row = $this->find($payoutRequest);
        $row->loadMissing(['affiliate', 'payout']);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $open = $row->status === PayoutRequestStatus::Pending && $admin->can('affiliates.payouts.manage');

        return $this->remoteModal($request, $open ? 'affiliates::admin.payouts._pay-form' : 'affiliates::admin.payouts._show', [
            'affiliate' => $row->affiliate,
            'payoutRequest' => $row,
            'minimum' => $this->settings->minPayout(),
            'methods' => PayoutMethod::options(),
            'formAction' => route('admin.affiliates.payouts.pay', $row->id),
            'rejectUrl' => route('admin.affiliates.payouts.reject.form', $row->id),
        ], $this->page($row->reference()));
    }

    public function pay(PayoutFormRequest $request, int $payoutRequest): JsonResponse
    {
        $row = $this->find($payoutRequest);
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $affiliate = $row->affiliate ?? abort(404);
        $payout = $this->service->pay($affiliate, $request->toData(), new AdminActor($admin->id, $admin->name), $row);

        return JsonResponder::success((string) __('affiliates::admin.payout.paid', ['amount' => $payout->money()->format()]));
    }

    public function rejectForm(Request $request, int $payoutRequest): View
    {
        $row = $this->find($payoutRequest);
        $row->loadMissing('affiliate');

        return $this->remoteModal($request, 'affiliates::admin.payouts._reject-form', ['payoutRequest' => $row], $this->page($row->reference()));
    }

    public function reject(NoteRequest $request, int $payoutRequest): JsonResponse
    {
        $row = $this->service->reject($this->find($payoutRequest), $request->note());

        return JsonResponder::success((string) __('affiliates::admin.payouts.rejected', ['reference' => $row->reference()]));
    }

    public function attachment(int $payout): StreamedResponse
    {
        /** @var AffiliatePayout|null $row */
        $row = $this->payouts->find($payout);

        abort_if($row === null || $row->attachment === null || ! Storage::disk(PayoutService::DISK)->exists($row->attachment), 404);

        return Storage::disk(PayoutService::DISK)->download($row->attachment, $row->attachment_name ?? basename($row->attachment));
    }

    private function find(int $id): AffiliatePayoutRequest
    {
        /** @var AffiliatePayoutRequest|null $row */
        $row = $this->requests->find($id);

        return $row ?? abort(404);
    }

    private function page(string $title): RemoteModalPage
    {
        return new RemoteModalPage('admin', $title, route('admin.affiliates.payouts.index'), 'admin-affiliates', null, null, [
            __('navigation.admin.affiliates') => null,
            __('navigation.admin.affiliate_payouts') => route('admin.affiliates.payouts.index'),
            $title => null,
        ]);
    }
}
