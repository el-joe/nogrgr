<?php

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\DataTables\PaymentLogsDataTable;
use App\Modules\Billing\DTOs\PaymentStatusChangeData;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\PaymentLogStatus;
use App\Modules\Billing\Http\Requests\Admin\PaymentStatusRequest;
use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Services\InvoiceQueryService;
use App\Modules\Billing\Services\PaymentLogService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payment logs (ADM-055 list, ADM-055a detail + status change; refund → affiliate
 * reversal event). Permissions: finance.payment-logs.view / .manage.
 */
class PaymentLogController extends Controller
{
    public function __construct(
        private readonly PaymentLogService $service,
        private readonly InvoiceQueryService $query,
    ) {}

    public function index(): View
    {
        return view('billing::admin.payment-logs.index', [
            'statuses' => PaymentLogStatus::options(),
            'gateways' => PaymentGateway::options(),
        ]);
    }

    public function data(Request $request, PaymentLogsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ['status', 'gateway', 'search']);
    }

    public function show(int $paymentLog): View
    {
        return view('billing::admin.payment-logs.show', ['log' => $this->query->paymentLog($paymentLog)]);
    }

    public function updateStatus(PaymentStatusRequest $request, PaymentLog $paymentLog): JsonResponse
    {
        $this->service->changeStatus($paymentLog, PaymentStatusChangeData::fromRequest($request));

        return JsonResponder::success(__('billing::payments.status_changed'), null, route('admin.finance.payment-logs.show', $paymentLog));
    }
}
