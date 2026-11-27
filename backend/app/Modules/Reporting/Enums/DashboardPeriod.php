<?php

namespace App\Modules\Reporting\Enums;

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Shared\Enums\Concerns\HasEnumHelpers;
use Carbon\CarbonImmutable;

/**
 * Period selector of the vendor dashboard "Your store's performance" block (VEN-009,
 * client frame 12:11267 "12 Months" select). Windows end today, inclusive.
 */
enum DashboardPeriod: string
{
    use HasEnumHelpers;

    case Days7 = '7d';
    case Days30 = '30d';
    case Days90 = '90d';
    case Months12 = '12m';

    public const DEFAULT = self::Months12;

    public function label(): string
    {
        return __('reporting::vendor_dashboard.periods.'.$this->value);
    }

    public static function fromQuery(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::DEFAULT) : self::DEFAULT;
    }

    public function filters(int $tenantId, ?CarbonImmutable $today = null): ReportFilterData
    {
        $end = $today ?? CarbonImmutable::today();
        $start = match ($this) {
            self::Days7 => $end->subDays(6),
            self::Days30 => $end->subDays(29),
            self::Days90 => $end->subDays(89),
            self::Months12 => $end->startOfMonth()->subMonths(11),
        };

        return new ReportFilterData($start->toDateString(), $end->toDateString(), $tenantId);
    }
}
