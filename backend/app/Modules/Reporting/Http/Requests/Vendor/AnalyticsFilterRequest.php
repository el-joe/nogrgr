<?php

namespace App\Modules\Reporting\Http\Requests\Vendor;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Http\Requests\Admin\ReportFilterRequest;

/**
 * Vendor analytics filters: ?from=&to= only (same 3-year cap as the admin reports). The
 * store is always the current tenant; a `tenant_id` / `country_id` in the query is ignored.
 */
class AnalyticsFilterRequest extends ReportFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function filters(): ReportFilterData
    {
        $validated = $this->validated();

        return ReportFilterData::fromInput([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'tenant_id' => (int) tenant()?->getTenantKey(),
        ]);
    }
}
