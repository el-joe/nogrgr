<?php

namespace App\Modules\Catalog\Http\Controllers\Vendor;

use App\Modules\Catalog\DTOs\Vendor\StorePriceRow;
use App\Modules\Catalog\Http\Requests\Vendor\MarkupRequest;
use App\Modules\Catalog\Models\StorePriceListRun;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Catalog\Services\Vendor\StorePriceListService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Price list modal (VEN-010a): markup mode / value / scope, live preview, queued apply
 * with progress polling.
 */
class PriceListController extends Controller
{
    public function __construct(
        private readonly StoreCatalogService $catalog,
        private readonly StorePriceListService $prices,
    ) {}

    public function show(Request $request, int $product, StoreCatalogReader $reader): View
    {
        $storeProduct = $this->catalog->find($product);
        $this->authorizeForUser($request->user('vendor'), 'view', $storeProduct);

        return view('catalog::vendor.products._price-list', [
            'product' => $storeProduct,
            'view' => $reader->view($storeProduct),
            'rows' => $this->prices->preview($storeProduct),
        ]);
    }

    public function preview(MarkupRequest $request, int $product): JsonResponse
    {
        $storeProduct = $this->catalog->find($product);

        return JsonResponder::success(data: [
            'rows' => array_map(static fn (StorePriceRow $row): array => $row->toArray(), $this->prices->preview($storeProduct, $request->toData())),
        ]);
    }

    public function store(MarkupRequest $request): JsonResponse
    {
        $run = $this->prices->start($request->productIds(), $request->toData(), $request->user('vendor')?->getAuthIdentifier());

        return JsonResponder::success((string) __('catalog::vendor.price_list.started'), $this->runPayload($run->refresh()));
    }

    public function run(string $run): JsonResponse
    {
        $found = $this->prices->find($run) ?? abort(404);

        return JsonResponder::success(data: $this->runPayload($found));
    }

    /**
     * @return array{id: string, status: string, label: string, percent: int, processed: int, total: int, finished: bool, url: string}
     */
    private function runPayload(StorePriceListRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'label' => $run->status->label(),
            'percent' => $run->percent(),
            'processed' => $run->processed,
            'total' => $run->total,
            'finished' => $run->status->isFinished(),
            'url' => vendor_route('catalog.products.price-list.run', ['run' => $run->id]),
        ];
    }
}
