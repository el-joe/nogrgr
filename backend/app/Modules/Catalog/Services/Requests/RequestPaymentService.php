<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\DTOs\InvoiceLineData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Services\InvoiceService;
use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\DTOs\Requests\PaymentRequestData;
use App\Modules\Catalog\Enums\PaymentRequestStatus;
use App\Modules\Catalog\Events\Requests\RequestPaymentRequested;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Modules\Catalog\Repositories\Contracts\RequestPaymentRepositoryContract;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment requests on manufacturing / brand requests. Creating one issues a Billing
 * invoice (InvoiceService, billable = the payment row) that the vendor pays later;
 * InvoicePaid settles it (MarkRequestPaymentPaid), cancelling voids the invoice.
 */
class RequestPaymentService extends BaseService
{
    public function __construct(
        private readonly RequestPaymentRepositoryContract $payments,
        private readonly InvoiceService $invoices,
        private readonly RequestThreadService $threads,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    /**
     * @return Collection<int, RequestPayment>
     */
    public function forRequest(Model&ThreadedRequest $request): Collection
    {
        return $this->payments->forRequest($request);
    }

    public function find(Model&ThreadedRequest $request, int $id): ?RequestPayment
    {
        $payment = $this->payments->find($id);

        return $payment !== null && $payment->requestable_type === $request->getMorphClass() && $payment->requestable_id === (int) $request->getKey()
            ? $payment
            : null;
    }

    public function create(Model&ThreadedRequest $request, PaymentRequestData $data, ?Admin $admin): RequestPayment
    {
        if (! $request->kind()->hasPayments()) {
            throw RequestRuleException::paymentsNotSupported();
        }

        $tenant = $this->tenants->find($request->tenantId());
        $tenant = $tenant instanceof Tenant ? $tenant : null;

        $payment = $this->transaction(function () use ($request, $data, $admin, $tenant): RequestPayment {
            $payment = $this->payments->createFor($request, [
                'label' => $data->label,
                'amount' => Money::ofMinor($data->amountMinor),
                'currency' => Money::BASE_CURRENCY,
                'status' => PaymentRequestStatus::Pending,
                'note' => $data->note,
                'created_by' => $admin?->id,
                'due_at' => $data->dueAt,
            ]);

            $invoice = $this->invoices->issue(new InvoiceDraftData(
                tenantId: $request->tenantId(),
                type: InvoiceType::Manual,
                lines: [new InvoiceLineData($request->reference().' · '.$data->label, $data->amountMinor)],
                billable: $payment,
                taxRateBp: 0,
                dueAt: $data->dueAt,
                billToName: $tenant?->name,
                billToEmail: $tenant?->owner_email,
            ));

            $this->payments->update($payment, ['invoice_id' => $invoice->id, 'due_at' => $invoice->due_at]);
            $this->dispatchAfterCommit(new RequestPaymentRequested($request, $payment));

            return $payment;
        }, $request->getConnectionName());

        $this->threads->pushUpdate($request, 'payment');

        return $payment;
    }

    public function cancel(Model&ThreadedRequest $request, RequestPayment $payment): void
    {
        if ($payment->status !== PaymentRequestStatus::Pending) {
            throw RequestRuleException::paymentNotCancellable();
        }

        $this->transaction(function () use ($request, $payment): void {
            $invoice = $payment->invoice;

            if ($invoice instanceof Invoice && $invoice->status === InvoiceStatus::Unpaid) {
                $this->invoices->void($invoice);
            }

            $this->payments->update($payment, ['status' => PaymentRequestStatus::Cancelled, 'cancelled_at' => now()]);
            $this->dispatchAfterCommit(new RequestPaymentRequested($request, $payment, cancelled: true));
        }, $request->getConnectionName());

        $this->threads->pushUpdate($request, 'payment');
    }

    /**
     * InvoicePaid → the payment request is settled.
     */
    public function markPaidByInvoice(Invoice $invoice): void
    {
        $payment = $this->payments->findByInvoice($invoice->id);

        if ($payment === null || $payment->status !== PaymentRequestStatus::Pending) {
            return;
        }

        $this->payments->update($payment, ['status' => PaymentRequestStatus::Paid, 'paid_at' => $invoice->paid_at ?? now()]);
        $request = $payment->requestable()->withoutGlobalScopes()->first();

        if ($request instanceof ThreadedRequest) {
            $this->threads->pushUpdate($request, 'payment');
        }
    }
}
