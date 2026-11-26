<?php

namespace App\Modules\Affiliates\Http\Controllers\Admin;

use App\Modules\Affiliates\DataTables\AffiliatesDataTable;
use App\Modules\Affiliates\DTOs\AdminActor;
use App\Modules\Affiliates\Enums\AffiliateType;
use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Affiliates\Enums\RateType;
use App\Modules\Affiliates\Http\Requests\PayoutFormRequest;
use App\Modules\Affiliates\Http\Requests\UpdateAffiliateRequest;
use App\Modules\Affiliates\Repositories\Contracts\AffiliateRepositoryContract;
use App\Modules\Affiliates\Services\AffiliateAccountService;
use App\Modules\Affiliates\Services\PayoutService;
use App\Modules\Affiliates\Services\ProgramSettingsService;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Affiliates › Accounts (ADM-060, Figma Admin Panel 74:66931): tabs all /
 * vendor-linked / independent / pending / suspended, search + type / status filters,
 * detail, edit (ADM-060a: commission + status), pay out (ADM-060b) and the program
 * settings button (ADM-060c, ProgramSettingsController).
 */
class AffiliateController extends Controller
{
    use RendersRemoteModals;

    private const TABS = ['all', 'vendor', 'independent', 'pending', 'suspended'];

    public function __construct(
        private readonly AffiliateRepositoryContract $affiliates,
        private readonly AffiliateAccountService $accounts,
        private readonly PayoutService $payouts,
        private readonly ProgramSettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'all';

        return view('affiliates::admin.accounts.index', [
            'counts' => $this->affiliates->counts(),
            'tab' => $tab,
            'types' => AffiliateType::options(),
            'statuses' => AccountStatus::options(),
        ]);
    }

    public function data(Request $request, AffiliatesDataTable $table): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $table->abilities($admin->can('affiliates.manage'), $admin->can('affiliates.payouts.manage'))
            ->toJsonFromRequest($request, AffiliatesDataTable::FILTERS);
    }

    public function show(Request $request, int $affiliate): View
    {
        $row = $this->find($affiliate);
        $row->loadCount(['clicks', 'conversions']);

        return $this->remoteModal($request, 'affiliates::admin.accounts._show', [
            'affiliate' => $row,
            'commission' => $this->accounts->commissionLabel($row),
            'referralUrl' => route('central.home', ['ref' => $row->code]),
            'recent' => $row->conversions()->with('tenant')->latest('id')->limit(5)->get(),
        ], $this->page($row->name));
    }

    public function edit(Request $request, int $affiliate): View
    {
        $row = $this->find($affiliate);

        return $this->remoteModal($request, 'affiliates::admin.accounts._edit-form', [
            'affiliate' => $row,
            'types' => RateType::options(),
            'statuses' => AccountStatus::options(),
            'defaultLabel' => $this->accounts->commissionLabel(new Affiliate),
        ], $this->page($row->name));
    }

    public function update(UpdateAffiliateRequest $request, int $affiliate): JsonResponse
    {
        $row = $this->accounts->update($this->find($affiliate), $request->toData());

        return JsonResponder::success((string) __('affiliates::admin.accounts.updated', ['name' => $row->name]));
    }

    public function payoutForm(Request $request, int $affiliate): View
    {
        $row = $this->find($affiliate);

        return $this->remoteModal($request, 'affiliates::admin.payouts._pay-form', [
            'affiliate' => $row,
            'payoutRequest' => null,
            'minimum' => $this->settings->minPayout(),
            'methods' => PayoutMethod::options(),
            'formAction' => route('admin.affiliates.payout.store', $row->id),
            'rejectUrl' => null,
        ], $this->page($row->name));
    }

    public function payout(PayoutFormRequest $request, int $affiliate): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $payout = $this->payouts->pay($this->find($affiliate), $request->toData(), new AdminActor($admin->id, $admin->name));

        return JsonResponder::success((string) __('affiliates::admin.payout.paid', ['amount' => $payout->money()->format()]));
    }

    private function find(int $id): Affiliate
    {
        return $this->affiliates->findById($id) ?? abort(404);
    }

    private function page(string $title): RemoteModalPage
    {
        return new RemoteModalPage('admin', $title, route('admin.affiliates.index'), 'admin-affiliates', null, null, [
            __('navigation.admin.affiliates') => null,
            __('navigation.admin.affiliate_accounts') => route('admin.affiliates.index'),
            $title => null,
        ]);
    }
}
