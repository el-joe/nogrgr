<?php

namespace App\Modules\Storefront\DataTables;

use App\Modules\Storefront\DTOs\VendorBannerRow;
use App\Modules\Storefront\Enums\BannerPlacement;
use App\Modules\Storefront\Services\Banners\VendorBannerPageService;
use App\Shared\DataTables\DataTableFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\CollectionDataTable;

/**
 * Vendor › Your store › Banners (VEN-035, Figma Vendor Panel 85:108271): one card row per
 * banner of the country tab + placement in storefront order — the store's own banners
 * (drag handle, status, on / off, edit / delete) merged with the platform's default
 * banners (read-only, "Admin" badge, show / hide on this store). The merged list spans
 * the tenant and central databases, so it is served from memory (it is small).
 */
final class VendorBannersDataTable
{
    public const FILTERS = ['search', 'country_id', 'placement'];

    public const COLUMNS = ['banner', 'status', 'actions'];

    public function __construct(private readonly VendorBannerPageService $pages) {}

    public function toJsonFromRequest(Request $request, bool $canManage): JsonResponse
    {
        $filters = DataTableFilters::fromRequest($request, self::FILTERS);
        $country = VendorBannerPageService::countryOrDefault($filters['country_id'] ?? null, $this->pages->countries());
        $placement = BannerPlacement::tryFrom((string) ($filters['placement'] ?? '')) ?? BannerPlacement::HomeHero;
        $search = is_string($filters['search'] ?? null) ? $filters['search'] : null;

        $rows = $this->pages->rows($placement, $country, $search)->values()->map(static fn (VendorBannerRow $row): array => [
            'id' => $row->banner->id,
            'source' => $row->source->value,
            'sortable' => $canManage && ! $row->isCentral(),
            'banner' => view('storefront::vendor.banners._card-cell', ['row' => $row, 'canManage' => $canManage])->render(),
            'status' => view('storefront::vendor.banners._status-cell', ['row' => $row, 'canManage' => $canManage])->render(),
            'actions' => view('storefront::vendor.banners._actions-cell', ['row' => $row, 'canManage' => $canManage])->render(),
        ]);

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->order(static fn (): null => null)
            ->setRowAttr([
                'data-id' => static fn (array $row): int => $row['id'],
                'data-source' => static fn (array $row): string => $row['source'],
                'data-sort-item' => static fn (array $row): ?string => $row['sortable'] ? '1' : null,
            ])
            ->rawColumns(self::COLUMNS)
            ->toJson();
    }
}
