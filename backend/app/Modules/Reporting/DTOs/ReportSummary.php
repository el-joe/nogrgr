<?php

namespace App\Modules\Reporting\DTOs;

/**
 * Everything a report tab shows above its table (Figma 75:68061 / 75:69019 / 75:69998).
 *
 * kpis   — KPI cards: key, label, value, raw, format, icon, delta (|null), spark
 * tiles  — compact stat tiles: label, value, tone (neutral|success|danger|warning)
 * charts — x-ui::chart-card specs: id, title, subtitle, type, labels, series, total?, wide
 * funnel — label / value / display / percent bars (affiliates funnel)
 * leaders — ranked rows: name, slug, value, detail (vendor leaderboard)
 */
final readonly class ReportSummary
{
    /**
     * @param  list<array{key: string, label: string, value: string, raw: int|float, format: string, icon: string, delta: float|null, spark: list<int|float>}>  $kpis
     * @param  list<array{label: string, value: string, tone: string}>  $tiles
     * @param  list<array{id: string, title: string, subtitle: string|null, type: string, labels: list<string>, series: list<array<string, mixed>>, total?: string|null, wide?: bool}>  $charts
     * @param  list<array{label: string, display: string, percent: float}>  $funnel
     * @param  list<array{rank: int, name: string, slug: string, value: string, detail: string, percent: float}>  $leaders
     */
    public function __construct(
        public array $kpis = [],
        public array $tiles = [],
        public array $charts = [],
        public array $funnel = [],
        public array $leaders = [],
    ) {}

    /**
     * Whether the period has any data at all (drives the page-level empty state).
     */
    public function isEmpty(): bool
    {
        foreach ($this->kpis as $kpi) {
            if ((float) $kpi['raw'] !== 0.0) {
                return false;
            }
        }

        foreach ($this->funnel as $step) {
            if ($step['percent'] > 0) {
                return false;
            }
        }

        return $this->kpis !== [] || $this->funnel !== [];
    }
}
