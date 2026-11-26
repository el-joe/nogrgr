<?php

namespace App\Modules\Reporting\Services\Reports;

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\TranslationProvider;
use App\Modules\Reporting\DTOs\ReportColumn;
use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\DTOs\ReportSummary;
use App\Modules\Reporting\Repositories\Contracts\AiUsageReportRepositoryContract;
use App\Modules\Reporting\Support\ReportFormat;
use Illuminate\Database\Query\Builder;

/**
 * ADM-070l AI usage (decision #44 usage / token logs): calls and tokens per store,
 * feature, provider and model from the AI usage log. Platform rows (admin catalog /
 * static-key translations) show as "Platform". No cost: providers' prices are not
 * configured, so the tab reports tokens only.
 */
final class AiUsageReport extends BaseReport
{
    public function __construct(private readonly AiUsageReportRepositoryContract $usage) {}

    public function summary(ReportFilterData $filters): ReportSummary
    {
        $now = $this->usage->totals($filters);
        $before = $this->usage->totals($filters->previous());
        $features = [];

        foreach ($this->usage->tokensByFeature($filters) as $feature => $tokens) {
            $features[AiFeature::tryFrom((string) $feature)?->label() ?? (string) $feature] = $tokens;
        }

        return new ReportSummary(
            kpis: [
                $this->kpi('ai_calls', $now['calls'], $before['calls'], 'number', 'sparkles'),
                $this->kpi('ai_tokens', $now['prompt'] + $now['completion'], $before['prompt'] + $before['completion'], 'number', 'zap'),
                $this->kpi('ai_prompt_tokens', $now['prompt'], $before['prompt'], 'number', 'upload'),
                $this->kpi('ai_completion_tokens', $now['completion'], $before['completion'], 'number', 'download'),
            ],
            charts: $features === [] ? [] : [
                $this->donut('ai_features', $features, ReportFormat::number(array_sum($features))),
            ],
        );
    }

    public function tableTitle(): string
    {
        return __('reporting::reports.tables.ai_usage');
    }

    public function columns(ReportFilterData $filters): array
    {
        return [
            new ReportColumn('tenant_name', __('reporting::reports.columns.store'), 'text', true, static fn (object $row): string => $row->tenant_name ?? __('reporting::reports.platform'), static fn (object $row): ?string => isset($row->tenant_slug) ? (string) $row->tenant_slug : null),
            new ReportColumn('feature', __('reporting::reports.columns.ai_feature'), 'text', true, static fn (object $row): string => AiFeature::tryFrom((string) $row->feature)?->label() ?? (string) $row->feature),
            new ReportColumn('provider', __('reporting::reports.columns.ai_provider'), 'text', true, static fn (object $row): string => TranslationProvider::tryFrom((string) $row->provider)?->label() ?? (string) $row->provider, static fn (object $row): string => (string) $row->model),
            new ReportColumn('calls', __('reporting::reports.columns.ai_calls'), 'number'),
            new ReportColumn('prompt_tokens', __('reporting::reports.columns.ai_prompt_tokens'), 'number'),
            new ReportColumn('completion_tokens', __('reporting::reports.columns.ai_completion_tokens'), 'number'),
            new ReportColumn('total_tokens', __('reporting::reports.columns.ai_total_tokens'), 'number'),
        ];
    }

    public function rows(ReportFilterData $filters): Builder
    {
        return $this->usage->grouped($filters);
    }

    public function defaultOrder(): array
    {
        return [6, 'desc'];
    }
}
