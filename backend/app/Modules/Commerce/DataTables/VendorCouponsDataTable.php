<?php

namespace App\Modules\Commerce\DataTables;

use App\Modules\Commerce\DataTables\Concerns\RendersCollectionTable;
use App\Modules\Commerce\DTOs\Promotions\PromotionCoupon;
use App\Modules\Commerce\Enums\CouponStatus;
use App\Modules\Commerce\Services\Promotions\VendorPromotionPageService;
use App\Modules\Commerce\Support\PromotionFormatter;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Ads & Marketing › Coupons (VEN-040, Figma 87:107268): "My coupons" (store,
 * view / edit / delete) or "Nogrgr coupons" (central, read-only, hide / unhide; hidden
 * rows stay listed and tagged). Columns: code + reach, discount, min spend, usage, ends,
 * status. Filters: tab, code search, status, country (store's selling countries).
 */
final class VendorCouponsDataTable
{
    use RendersCollectionTable;

    public const FILTERS = ['search', 'tab', 'status', 'country_id'];

    public const COLUMNS = ['code', 'discount', 'min_spend', 'usage', 'ends', 'status', 'actions'];

    public function __construct(private readonly VendorPromotionPageService $pages) {}

    public function toJsonFromRequest(Request $request, bool $canManage): JsonResponse
    {
        $filters = DataTableFilters::fromRequest($request, self::FILTERS);
        $tab = VendorPromotionPageService::tab($filters['tab'] ?? null);
        $country = filter_var($filters['country_id'] ?? null, FILTER_VALIDATE_INT);
        $status = CouponStatus::tryFrom(is_string($filters['status'] ?? null) ? $filters['status'] : '');
        $search = is_string($filters['search'] ?? null) ? mb_strtolower(trim($filters['search'])) : '';
        $codes = $this->pages->countryCodes();

        $items = $this->pages->coupons($tab, $country === false ? null : $country)
            ->filter(static fn (array $row): bool => ($search === '' || str_contains(mb_strtolower($row['coupon']->code.' '.$row['coupon']->name), $search))
                && ($status === null || $row['coupon']->status($row['used']) === $status));

        return $this->respond($request, $items, [
            'code' => static fn (array $row): string => $row['coupon']->code,
            'discount' => static fn (array $row): string => self::discountKey($row['coupon']),
            'min_spend' => static fn (array $row): int => $row['coupon']->minSpend->minor,
            'usage' => static fn (array $row): int => $row['used'],
            'ends' => static fn (array $row): int => $row['coupon']->endsAt?->getTimestamp() ?? PHP_INT_MAX,
            'status' => static fn (array $row): string => $row['coupon']->status($row['used'])->value,
        ], fn (array $row): array => $this->render($row['coupon'], $row['used'], $codes, $canManage), self::COLUMNS);
    }

    /**
     * @param  array<int, string>  $codes  country id => ISO code
     * @return array<string, string>
     */
    private function render(PromotionCoupon $coupon, int $used, array $codes, bool $canManage): array
    {
        return [
            'code' => view('commerce::vendor.coupons._code-cell', ['coupon' => $coupon, 'codes' => $codes])->render(),
            'discount' => e(PromotionFormatter::discount($coupon->type, $coupon->percentage, $coupon->amount, $coupon->maxDiscount)),
            'min_spend' => '<span class="font-semibold text-ink" dir="ltr">'.e($coupon->minSpend->format()).'</span>',
            'usage' => '<span dir="ltr">'.e(PromotionFormatter::usage($used, $coupon->usageLimit)).'</span>',
            'ends' => e($coupon->endsAt !== null ? $coupon->endsAt->locale(app()->getLocale())->isoFormat('DD MMM') : '—'),
            'status' => view('commerce::admin.partials._status-cell', ['status' => $coupon->status($used)])->render(),
            'actions' => view('commerce::vendor.coupons._actions-cell', ['coupon' => $coupon, 'canManage' => $canManage])->render(),
        ];
    }

    /**
     * Sorts by type, then by the amount (fixed, minor units) or the percentage.
     */
    private static function discountKey(PromotionCoupon $coupon): string
    {
        $value = $coupon->amount !== null ? $coupon->amount->minor : (int) round($coupon->percentage * 100);

        return $coupon->type->value.':'.str_pad((string) $value, 12, '0', STR_PAD_LEFT);
    }
}
