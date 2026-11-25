<?php

namespace App\Modules\Billing\Http\Controllers\Admin\Finance;

use App\Modules\Billing\DataTables\Finance\SettlementsDataTable;
use App\Modules\Billing\Enums\SettlementMethod;
use App\Modules\Billing\Http\Requests\Finance\StoreSearchRequest;
use App\Modules\Billing\Services\Finance\FinancePageService;
use App\Modules\Commerce\Enums\SettlementStatus;
use App\Modules\Commerce\Repositories\Contracts\OrderSettlementRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Http\Concerns\RendersRemoteModals;
use App\Shared\Http\Controller;
use App\Shared\Http\RemoteModalPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin › Finance › Settlements vendor → platform (ADM-051, Figma 74:60809): status tabs,
 * search, gateway / store / status filters and the settlement detail modal (orders
 * covered, proof file).
 */
class SettlementController extends Controller
{
    use RendersRemoteModals;

    public function __construct(
        private readonly FinancePageService $pages,
        private readonly OrderSettlementRepositoryContract $settlements,
    ) {}

    public function index(Request $request): View
    {
        $status = SettlementStatus::tryFrom((string) $request->query('status'));

        return view('billing::admin.finance.settlements.index', [
            'stats' => $this->settlements->stats(),
            'active' => $status->value ?? 'all',
            'gateways' => self::gatewayOptions(),
        ]);
    }

    public function data(Request $request, SettlementsDataTable $table): JsonResponse
    {
        return $table->forTenant(null, 'admin')->toJsonFromRequest($request, SettlementsDataTable::FILTERS);
    }

    public function stores(StoreSearchRequest $request): JsonResponse
    {
        return response()->json($this->pages->storeSearch($request->term(), $request->page()));
    }

    public function show(Request $request, string $reference): View
    {
        $rows = $this->pages->settlement($reference);

        return $this->remoteModal($request, 'billing::finance._settlement', [
            'rows' => $rows->load('tenant'),
            'attachmentUrl' => $rows->first()?->attachment_path !== null ? route('admin.finance.settlements.attachment', $reference) : null,
        ], new RemoteModalPage('admin', $reference, route('admin.finance.settlements.index'), 'admin-finance', null, null, [
            __('navigation.admin.finance') => null,
            __('navigation.admin.settlements') => route('admin.finance.settlements.index'),
            $reference => null,
        ]));
    }

    public function attachment(string $reference): StreamedResponse
    {
        $row = $this->pages->settlement($reference)->first();

        abort_if($row === null || $row->attachment_path === null || ! Storage::disk('central')->exists($row->attachment_path), 404);

        return Storage::disk('central')->download($row->attachment_path, $row->attachment_name ?? basename($row->attachment_path));
    }

    /**
     * @return array<string, string>
     */
    public static function gatewayOptions(): array
    {
        $options = [];

        foreach (PaymentGatewayCode::cases() as $code) {
            $options[$code->value] = $code->brand();
        }

        return $options + SettlementMethod::options();
    }
}
