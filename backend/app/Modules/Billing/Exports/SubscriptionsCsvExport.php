<?php

namespace App\Modules\Billing\Exports;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Repositories\Contracts\SubscriptionRepositoryContract;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subscriptions CSV (ADM-047 export) with the list's filters. UTF-8 with BOM so
 * Excel opens Arabic text correctly; amounts as USD decimals.
 */
final class SubscriptionsCsvExport
{
    public function __construct(private readonly SubscriptionRepositoryContract $subscriptions) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function download(array $filters): StreamedResponse
    {
        $query = $this->subscriptions->listing($filters)->orderByDesc('subscriptions.id');
        $filename = 'subscriptions-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('billing::subscriptions.columns.id'),
                __('billing::subscriptions.columns.store'),
                __('billing::subscriptions.columns.plan'),
                __('billing::subscriptions.fields.term'),
                __('billing::subscriptions.columns.type'),
                __('billing::subscriptions.columns.amount'),
                __('billing::subscriptions.columns.currency'),
                __('billing::subscriptions.columns.status'),
                __('billing::subscriptions.columns.period_start'),
                __('billing::subscriptions.columns.next_billing'),
                __('billing::subscriptions.columns.invoice'),
                __('billing::subscriptions.columns.created'),
            ], escape: '');

            $query->chunk(500, function ($rows) use ($out): void {
                /** @var Subscription $subscription */
                foreach ($rows as $subscription) {
                    fputcsv($out, [
                        $subscription->id,
                        (string) $subscription->tenant?->getAttribute('name'),
                        $subscription->plan->name,
                        $subscription->term->label(),
                        $subscription->kind()->label(),
                        $subscription->amount()->toDecimal(),
                        $subscription->currency,
                        $subscription->status->label(),
                        $subscription->current_period_starts_at?->toDateString() ?? '',
                        $subscription->nextBillingAt()?->toDateString() ?? '',
                        $subscription->latestInvoice->number ?? '',
                        $subscription->created_at?->toDateTimeString() ?? '',
                    ], escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
