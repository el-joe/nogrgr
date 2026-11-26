<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Support\ReportFormat;
use App\Modules\Reporting\Support\ReportMath;

/**
 * Builders shared by the report tabs: KPI cards with the delta vs the previous window and
 * a sparkline, stat tiles, and x-ui::chart-card specs (money series in USD major units).
 */
abstract class BaseReport implements Report
{
    /** Donut / categorical colours in the Figma order. */
    protected const SLICE_TONES = ['success', 'info', 'warning', 'danger', 'brand', 'muted'];

    /** @var array<int, string>|null */
    private ?array $countryNames = null;

    public function defaultOrder(): array
    {
        return [1, 'desc'];
    }

    /**
     * @param  'money'|'number'|'decimal'|'percent'  $format
     * @param  list<int|float>  $spark
     * @return array{key: string, label: string, value: string, raw: int|float, format: string, icon: string, delta: float|null, spark: list<int|float>}
     */
    protected function kpi(string $key, int|float $current, int|float $previous, string $format, string $icon, array $spark = []): array
    {
        return [
            'key' => $key,
            'label' => __('reporting::reports.kpi.'.$key),
            'value' => $this->display($current, $format),
            'raw' => $current,
            'format' => $format,
            'icon' => $icon,
            'delta' => ReportMath::delta($current, $previous),
            'spark' => $spark,
        ];
    }

    /**
     * @return array{label: string, value: string, tone: string}
     */
    protected function tile(string $key, string $value, string $tone = 'neutral'): array
    {
        return ['label' => __('reporting::reports.tiles.'.$key), 'value' => $value, 'tone' => $tone];
    }

    /**
     * Line / bar chart over the window's buckets.
     *
     * @param  list<array{label: string, data: list<int|float>, tone: string}>  $series
     * @return array<string, mixed>
     */
    protected function seriesChart(string $id, string $type, ReportFilterData $filters, array $series, bool $wide = true): array
    {
        return [
            'id' => $id,
            'title' => __('reporting::reports.charts.'.$id.'.title'),
            'subtitle' => __('reporting::reports.charts.'.$id.'.subtitle'),
            'type' => $type,
            'labels' => ReportFormat::bucketLabels($filters),
            'series' => $series,
            'wide' => $wide,
        ];
    }

    /**
     * Doughnut of label => value slices (top 5 + others).
     *
     * @param  array<string, int|float>  $slices
     * @return array<string, mixed>
     */
    protected function donut(string $id, array $slices, string $total): array
    {
        $slices = ReportMath::topSlices(array_filter($slices, static fn ($value): bool => $value > 0), 5, __('reporting::reports.others'));

        return [
            'id' => $id,
            'title' => __('reporting::reports.charts.'.$id.'.title'),
            'subtitle' => __('reporting::reports.charts.'.$id.'.subtitle'),
            'type' => 'doughnut',
            'labels' => array_map('strval', array_keys($slices)),
            'series' => [[
                'label' => __('reporting::reports.charts.'.$id.'.title'),
                'data' => array_values($slices),
                'tones' => array_slice(self::SLICE_TONES, 0, count($slices)),
            ]],
            'total' => $total,
            'wide' => false,
        ];
    }

    /**
     * Money series in USD (major units) for the chart axis.
     *
     * @param  list<int|float>  $minor
     * @return list<float>
     */
    protected function dollars(array $minor): array
    {
        return array_map(static fn ($value): float => round($value / 100, 2), $minor);
    }

    /**
     * @param  array<string, array<string, int>>  $daily
     * @return array<string, int>
     */
    protected function pick(array $daily, string $key): array
    {
        return array_map(static fn (array $day): int => (int) ($day[$key] ?? 0), $daily);
    }

    protected function countryName(int $id): string
    {
        $this->countryNames ??= app(CountryService::class)->options();

        return $id > 0 ? (string) ($this->countryNames[$id] ?? '#'.$id) : __('reporting::reports.unknown_country');
    }

    protected function display(int|float $value, string $format): string
    {
        return match ($format) {
            'money' => ReportFormat::money((int) $value),
            'percent' => ReportFormat::percent((float) $value),
            'decimal' => ReportFormat::number($value, 1),
            default => ReportFormat::number($value),
        };
    }
}
