<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\DTOs\InvoiceDraftData;
use App\Modules\Billing\Enums\InvoiceStatus;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\InvoicePaid;
use App\Modules\Billing\Events\InvoiceVoided;
use App\Modules\Billing\Exceptions\BillingRuleException;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Repositories\Contracts\InvoiceRepositoryContract;
use App\Modules\Billing\Support\InvoiceCalculator;
use App\Shared\Money\Money;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Issues and settles platform invoices (central connection). Totals come from
 * InvoiceCalculator; numbers are INV-<year>-<id, 4+ digits>.
 */
class InvoiceService extends BaseService
{
    public function __construct(private readonly InvoiceRepositoryContract $invoices) {}

    public function issue(InvoiceDraftData $draft): Invoice
    {
        $taxRate = $draft->taxRateBp ?? (int) config('billing.tax_rate_bp', 0);
        $totals = InvoiceCalculator::calculate($draft->lines, $taxRate, Money::BASE_CURRENCY);

        $invoice = $this->transaction(function () use ($draft, $taxRate, $totals): Invoice {
            $now = now();

            /** @var Invoice $invoice */
            $invoice = $this->invoices->create([
                'number' => 'TMP-'.Str::random(24),
                'tenant_id' => $draft->tenantId,
                'type' => $draft->type,
                'status' => InvoiceStatus::Unpaid,
                'billable_type' => $draft->billable?->getMorphClass(),
                'billable_id' => $draft->billable?->getKey(),
                'currency' => Money::BASE_CURRENCY,
                'subtotal_minor' => $totals['subtotal']->minor,
                'tax_rate_bp' => $taxRate,
                'tax_minor' => $totals['tax']->minor,
                'total_minor' => $totals['total']->minor,
                'bill_to_name' => $draft->billToName,
                'bill_to_email' => $draft->billToEmail,
                'issued_at' => $now,
                'due_at' => $draft->dueAt ?? $now->copy()->addDays((int) config('billing.invoice_due_days', 7)),
            ]);

            $this->invoices->update($invoice, ['number' => sprintf('INV-%d-%04d', $now->year, $invoice->id)]);

            foreach ($totals['lines'] as $index => $computed) {
                $invoice->items()->create([
                    'description' => $computed['line']->description,
                    'quantity' => $computed['line']->quantity,
                    'unit_amount_minor' => $computed['line']->unitAmountMinor,
                    'total_minor' => $computed['total']->minor,
                    'sort_order' => $index,
                ]);
            }

            $this->dispatchAfterCommit(new InvoiceIssued($invoice));

            return $invoice;
        }, $this->connection());

        return $invoice->load('items');
    }

    public function markPaid(Invoice $invoice, ?string $method = null, ?string $reference = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return $invoice;
        }

        $this->guard($invoice, InvoiceStatus::Paid);

        return $this->transaction(function () use ($invoice, $method, $reference): Invoice {
            $this->invoices->update($invoice, [
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'payment_method' => $method ?? $invoice->payment_method,
                'payment_reference' => $reference ?? $invoice->payment_reference,
            ]);
            $this->dispatchAfterCommit(new InvoicePaid($invoice));

            return $invoice;
        }, $this->connection());
    }

    public function void(Invoice $invoice): Invoice
    {
        $this->guard($invoice, InvoiceStatus::Void);

        $this->invoices->update($invoice, ['status' => InvoiceStatus::Void, 'voided_at' => now()]);
        event(new InvoiceVoided($invoice));

        return $invoice;
    }

    public function markRefunded(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Refunded) {
            return $invoice;
        }

        $this->guard($invoice, InvoiceStatus::Refunded);
        $this->invoices->update($invoice, ['status' => InvoiceStatus::Refunded]);

        return $invoice;
    }

    private function guard(Invoice $invoice, InvoiceStatus $next): void
    {
        if (! $invoice->status->canTransitionTo($next)) {
            throw BillingRuleException::invalidTransition($invoice->status->label(), $next->label());
        }
    }

    private function connection(): ?string
    {
        return (new Invoice)->getConnectionName();
    }
}
