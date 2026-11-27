<?php

namespace App\Modules\Inventory\Http\Controllers\Vendor;

use App\Modules\Inventory\Http\Requests\Vendor\StockThresholdRequest;
use App\Modules\Inventory\Services\Alerts\StockAlertService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;

/**
 * Store-wide low-stock alert threshold (card of Vendor › Settings › Notifications);
 * products with their own minimum stock keep it.
 */
class StockAlertSettingsController extends Controller
{
    public function __construct(private readonly StockAlertService $alerts) {}

    public function update(StockThresholdRequest $request): JsonResponse
    {
        $this->alerts->setStoreThreshold($request->threshold());

        return JsonResponder::success(__('inventory::alerts.threshold.saved'), ['low_stock_threshold' => $this->alerts->storeThreshold()]);
    }
}
