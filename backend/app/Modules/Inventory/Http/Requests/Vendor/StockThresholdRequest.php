<?php

namespace App\Modules\Inventory\Http\Requests\Vendor;

use App\Modules\Inventory\Services\Alerts\StockAlertService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT low_stock_threshold (0 = low-stock alerts off) of Vendor › Settings › Notifications.
 */
class StockThresholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['low_stock_threshold' => ['required', 'integer', 'min:0', 'max:'.StockAlertService::MAX_THRESHOLD]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['low_stock_threshold' => __('inventory::alerts.threshold.label')];
    }

    public function threshold(): int
    {
        return (int) $this->validated('low_stock_threshold');
    }
}
