<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\Enums\FulfilmentStatus;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Services\Promotions\PromotionPickerService;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Localization\Services\CountryService;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Shared\Money\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filters, tab counts and the CSV export of the admin order list and shipping queue
 * (all from order_facts).
 */
class AdminOrderListService
{
    public const TAB_STATUSES = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Returned];

    public function __construct(
        private readonly OrderFactRepositoryContract $facts,
        private readonly PromotionPickerService $picker,
        private readonly CountryService $countries,
        private readonly BranchService $branches,
    ) {}

    /**
     * @return array<string, array{label: string, count: int}>
     */
    public function statusTabs(): array
    {
        $counts = $this->facts->statusCounts();
        $tabs = ['all' => ['label' => (string) __('commerce::orders_admin.tabs.all'), 'count' => array_sum($counts)]];

        foreach (self::TAB_STATUSES as $status) {
            $tabs[$status->value] = ['label' => $status->label(), 'count' => $counts[$status->value] ?? 0];
        }

        return $tabs;
    }

    /**
     * @return array<string, array{label: string, count: int}>
     */
    public function queueTabs(): array
    {
        $counts = $this->facts->queueCounts();

        return collect(['to_ship', 'in_transit', 'delayed', 'delivered'])->mapWithKeys(static fn (string $tab): array => [
            $tab => ['label' => (string) __('commerce::fulfilment.queue.tabs.'.$tab), 'count' => $counts[$tab] ?? 0],
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    public function gatewayOptions(): array
    {
        $options = [];

        foreach (PaymentGatewayCode::cases() as $code) {
            $options[$code->value] = $code->brand();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function storeOptions(?string $term = null): array
    {
        $options = [];

        foreach ($this->picker->stores($term)['results'] as $row) {
            $options[$row['id']] = $row['text'];
        }

        return $options;
    }

    /**
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function storeSearch(?string $term, int $page): array
    {
        return $this->picker->stores($term, $page);
    }

    /**
     * @return array<int|string, string>
     */
    public function countryOptions(): array
    {
        return $this->countries->options();
    }

    /**
     * @return array<int, string>
     */
    public function branchOptions(): array
    {
        $options = [];

        foreach ($this->branches->active() as $branch) {
            $options[(int) $branch->id] = (string) $branch->name;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function fulfilmentOptions(): array
    {
        return FulfilmentStatus::options();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function export(array $filters): StreamedResponse
    {
        $query = $this->facts->listing($filters)->orderByDesc('placed_at');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['order', 'store', 'customer', 'city', 'status', 'payment', 'gateway', 'total_usd', 'platform_usd', 'vendor_usd', 'placed_at']);

            $query->chunk(500, static function ($facts) use ($out): void {
                /** @var OrderFact $fact */
                foreach ($facts as $fact) {
                    fputcsv($out, [
                        $fact->order_number,
                        (string) $fact->tenant?->getAttribute('name'),
                        (string) $fact->customer_name,
                        (string) $fact->customer_city,
                        $fact->status,
                        $fact->payment_status,
                        (string) $fact->payment_gateway,
                        Money::ofMinor($fact->grand_total)->toDecimal(),
                        Money::ofMinor($fact->platform_profit)->toDecimal(),
                        Money::ofMinor($fact->vendor_profit)->toDecimal(),
                        (string) $fact->placed_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($out);
        }, 'orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
