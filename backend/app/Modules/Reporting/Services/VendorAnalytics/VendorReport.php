<?php

namespace App\Modules\Reporting\Services\VendorAnalytics;

use App\Modules\Reporting\Services\Reports\BaseReport;
use App\Modules\Reporting\Support\ReportFormat;

/**
 * One vendor analytics tab (VEN-045 / VEN-024): same summary / table / CSV contract as the
 * admin reports, labels from `reporting::vendor_analytics`, data of one store only.
 */
abstract class VendorReport extends BaseReport
{
    protected function lang(): string
    {
        return 'reporting::vendor_analytics';
    }

    /**
     * Bar chart over named categories (carriers, products) instead of period buckets.
     *
     * @param  array<string, int|float>  $values  label => value
     * @return array<string, mixed>
     */
    protected function categoryChart(string $id, array $values, string $seriesLabel, string $tone = 'brand'): array
    {
        return [
            'id' => $id,
            'title' => __($this->lang().'.charts.'.$id.'.title'),
            'subtitle' => __($this->lang().'.charts.'.$id.'.subtitle'),
            'type' => 'bar',
            'labels' => array_map('strval', array_keys($values)),
            'series' => [['label' => $seriesLabel, 'data' => array_values($values), 'tone' => $tone]],
            'wide' => false,
        ];
    }

    protected function date(mixed $value): string
    {
        return $value ? ReportFormat::day((string) $value) : '—';
    }
}
