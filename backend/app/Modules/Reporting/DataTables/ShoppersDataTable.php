<?php

namespace App\Modules\Reporting\DataTables;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ShopperStatus;
use App\Modules\Reporting\Services\ShopperService;
use App\Modules\Reporting\Support\ReportFormat;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\QueryDataTable;

/**
 * Shoppers table (ADM-043, Figma 73:52854): shopper (masked), store, country, orders,
 * spent, status. Search and filters are applied by the repository.
 */
final class ShoppersDataTable
{
    public function __construct(private readonly ShopperService $shoppers) {}

    /**
     * @param  array{search?: string|null, status?: string|null, new_only?: bool}  $options
     * @param  array<int, string>  $countries
     */
    public function toJson(ReportFilterData $filters, array $options, array $countries): JsonResponse
    {
        return (new QueryDataTable($this->shoppers->rows($filters, $options)))
            ->filter(static fn (): null => null, false)
            ->orderColumn('shopper', static fn ($query, string $order) => $query->orderBy('sh.customer_name', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('store', static fn ($query, string $order) => $query->orderBy('tenant_name', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('country', static fn ($query, string $order) => $query->orderBy('sh.country_id', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('orders', static fn ($query, string $order) => $query->orderBy('sh.orders', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('spent', static fn ($query, string $order) => $query->orderBy('sh.spent', $order === 'desc' ? 'desc' : 'asc'))
            ->orderColumn('status', static fn ($query, string $order) => $query->orderBy('shopper_status', $order === 'desc' ? 'desc' : 'asc'))
            ->addColumn('shopper', static fn (object $row): string => '<span class="grid min-w-0"><span class="truncate font-medium text-ink">'.e(ShopperService::maskName($row->customer_name)).'</span><span class="truncate text-xs text-ink-secondary" dir="ltr">'.e(ShopperService::maskEmail($row->customer_email)).'</span></span>')
            ->addColumn('store', static fn (object $row): string => '<span class="grid min-w-0"><span class="truncate font-medium text-ink">'.e((string) $row->tenant_name).'</span><span class="truncate text-xs text-ink-secondary" dir="ltr">'.e((string) $row->tenant_slug).'</span></span>')
            ->addColumn('country', static fn (object $row): string => e($countries[(int) $row->country_id] ?? '—'))
            ->addColumn('orders_cell', static fn (object $row): string => '<span class="font-medium tabular-nums text-ink" dir="ltr">'.e(ReportFormat::number((int) $row->orders)).'</span>')
            ->addColumn('spent_cell', static fn (object $row): string => '<span class="font-medium tabular-nums text-ink" dir="ltr">'.e(ReportFormat::money((int) $row->spent)).'</span>')
            ->addColumn('status_cell', static function (object $row): string {
                return self::badge(ShopperStatus::from((string) $row->shopper_status));
            })
            ->rawColumns(['shopper', 'store', 'orders_cell', 'spent_cell', 'status_cell'])
            ->toJson();
    }

    private static function badge(ShopperStatus $status): string
    {
        return view('reporting::admin.shoppers._status', ['status' => $status])->render();
    }
}
