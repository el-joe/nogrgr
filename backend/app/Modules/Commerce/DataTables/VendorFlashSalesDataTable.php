<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\DataTables\Concerns\RendersCollectionTable;
use App\Modules\Commerce\DTOs\Promotions\PromotionFlashSale;
use App\Modules\Commerce\Enums\FlashSaleStatus;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use App\Modules\Commerce\Support\PromotionFormatter;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Ads & Marketing › Flash sales (VEN-041, Figma 87:108154): "My flash sales"
 * (store, view / edit / delete) or "Nogrgr flash sales" (central, read-only, hide /
 * unhide). Columns: title + banner, discount, products, ends, status. Filters: tab,
 * search, status.
 */
final class VendorFlashSalesDataTable
{
    use RendersCollectionTable;

    public const FILTERS = ['search', 'tab', 'status'];

    public const COLUMNS = ['title', 'discount', 'products', 'ends', 'status', 'actions'];

    public function __construct(private readonly VendorPromotionPageService $pages) {}

    public function toJsonFromRequest(Request $request, bool $canManage): JsonResponse
    {
        $filters = DataTableFilters::fromRequest($request, self::FILTERS);
        $tab = VendorPromotionPageService::tab($filters['tab'] ?? null);
        $status = FlashSaleStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $search = is_string($filters['search'] ?? null) ? mb_strtolower(trim($filters['search'])) : '';

        $items = $this->pages->flashSales($tab)
            ->filter(static fn (PromotionFlashSale $sale): bool => ($search === '' || str_contains(mb_strtolower($sale->title), $search))
                && ($status === null || $sale->status() === $status));

        return $this->respond($request, $items, [
            'title' => static fn (PromotionFlashSale $sale): string => $sale->title,
            'discount' => static fn (PromotionFlashSale $sale): float => $sale->percentage,
            'products' => static fn (PromotionFlashSale $sale): int => count($sale->products),
            'ends' => static fn (PromotionFlashSale $sale): int => $sale->endsAt->getTimestamp(),
            'status' => static fn (PromotionFlashSale $sale): string => $sale->status()->value,
        ], fn (PromotionFlashSale $sale): array => $this->render($sale, $canManage), self::COLUMNS);
    }

    /**
     * @return array<string, string>
     */
    private function render(PromotionFlashSale $sale, bool $canManage): array
    {
        $status = $sale->status();

        return [
            'title' => view('commerce::vendor.flash-sales._title-cell', ['sale' => $sale])->render(),
            'discount' => '<span dir="ltr">'.e(PromotionFormatter::percent($sale->percentage)).'</span>',
            'products' => '<span class="font-semibold text-ink" dir="ltr">'.e(number_format(count($sale->products))).'</span>',
            'ends' => e($status === FlashSaleStatus::Ended
                ? $sale->endsAt->locale(app()->getLocale())->isoFormat('DD MMM YYYY')
                : ($status === FlashSaleStatus::Live ? PromotionFormatter::endsIn($sale->endsAt) : $sale->endsAt->locale(app()->getLocale())->isoFormat('DD MMM HH:mm'))),
            'status' => view('commerce::admin.partials._status-cell', ['status' => $status])->render(),
            'actions' => view('commerce::vendor.flash-sales._actions-cell', ['sale' => $sale, 'canManage' => $canManage])->render(),
        ];
    }
}
