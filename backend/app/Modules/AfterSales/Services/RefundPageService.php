<?php

namespace App\Modules\AfterSales\Services;

use App\Modules\AfterSales\Exceptions\AfterSalesException;
use App\Modules\AfterSales\Models\Refund;
use App\Modules\AfterSales\Repositories\Contracts\RefundRepositoryContract;
use App\Shared\Money\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * View data of the refunds pages (both panels): KPIs, one refund, CSV export.
 */
class RefundPageService
{
    public function __construct(private readonly RefundRepositoryContract $refunds) {}

    public function find(int $id): Refund
    {
        return $this->refunds->findForTenant($id) ?? throw AfterSalesException::rule('refund_not_found', 404);
    }

    /**
     * @return array{refunded_30d: int, pending: int, failed: int, pending_count: int, failed_count: int, counts: array<string, int>}
     */
    public function stats(): array
    {
        return $this->refunds->stats();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->refunds->listing($filters)->with('tenant')->orderByDesc('id');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['refund', 'order', 'store', 'source', 'method', 'gateway', 'amount_usd', 'status', 'requested_at', 'processed_at']);

            $query->chunk(500, static function ($refunds) use ($out): void {
                /** @var Refund $refund */
                foreach ($refunds as $refund) {
                    fputcsv($out, [
                        $refund->reference,
                        $refund->order_number,
                        (string) $refund->tenant?->getAttribute('name'),
                        $refund->source->value,
                        $refund->method->value,
                        (string) $refund->gateway,
                        Money::ofMinor($refund->amount, $refund->currency)->toDecimal(),
                        $refund->status->value,
                        $refund->requested_at->toDateTimeString(),
                        (string) $refund->processed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'refunds-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
