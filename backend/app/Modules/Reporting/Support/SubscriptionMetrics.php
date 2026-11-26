<?php

namespace App\Modules\Reporting\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * MRR / churn / trials from subscription lifecycles (unit tested). A subscription is
 * paying on a day when it exists, has not been cancelled or expired, is past its trial
 * and has an amount; yearly terms count 1/12 of their price per month.
 *
 * Rows: object{plan_id, plan_name, term, status, amount_minor, trial_ends_at, created_at, cancelled_at, expired_at}.
 */
final class SubscriptionMetrics
{
    public static function monthlyAmount(string $term, int $amount): int
    {
        return $term === 'yearly' ? (int) round($amount / 12) : $amount;
    }

    public static function isPaying(object $row, CarbonImmutable $at): bool
    {
        return self::exists($row, $at)
            && ! self::inTrial($row, $at)
            && (int) $row->amount_minor > 0;
    }

    public static function isTrialing(object $row, CarbonImmutable $at): bool
    {
        return self::exists($row, $at) && self::inTrial($row, $at);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public static function mrr(Collection $rows, CarbonImmutable $at): int
    {
        return (int) $rows->filter(static fn (object $row): bool => self::isPaying($row, $at))
            ->sum(static fn (object $row): int => self::monthlyAmount((string) $row->term, (int) $row->amount_minor));
    }

    /**
     * Paying subscriptions at the window start that ended inside the window.
     *
     * @param  Collection<int, object>  $rows
     */
    public static function churned(Collection $rows, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $rows->filter(static function (object $row) use ($start, $end): bool {
            $ended = self::endedAt($row);

            return self::isPaying($row, $start) && $ended !== null && $ended->betweenIncluded($start, $end);
        })->count();
    }

    /**
     * Churn rate in percent of the subscriptions paying at the window start.
     *
     * @param  Collection<int, object>  $rows
     */
    public static function churnRate(Collection $rows, CarbonImmutable $start, CarbonImmutable $end): float
    {
        $base = $rows->filter(static fn (object $row): bool => self::isPaying($row, $start))->count();

        return ReportMath::percent(self::churned($rows, $start, $end), $base);
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public static function trials(Collection $rows, CarbonImmutable $at): int
    {
        return $rows->filter(static fn (object $row): bool => self::isTrialing($row, $at))->count();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    public static function started(Collection $rows, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $rows->filter(static fn (object $row): bool => self::date($row->created_at)?->betweenIncluded($start, $end) ?? false)->count();
    }

    /**
     * Per plan: paying, trials, started, churned and MRR at the window end.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object{plan: string, active: int, trials: int, started: int, churned: int, mrr: int}>
     */
    public static function byPlan(Collection $rows, CarbonImmutable $start, CarbonImmutable $end, string $noPlan): Collection
    {
        return $rows->groupBy(static fn (object $row): string => (string) ($row->plan_name ?? $noPlan))
            ->map(static fn (Collection $plan, string $name): object => (object) [
                'plan' => $name,
                'active' => $plan->filter(static fn (object $row): bool => self::isPaying($row, $end))->count(),
                'trials' => self::trials($plan, $end),
                'started' => self::started($plan, $start, $end),
                'churned' => self::churned($plan, $start, $end),
                'mrr' => self::mrr($plan, $end),
            ])
            ->sortByDesc('mrr')
            ->values();
    }

    private static function exists(object $row, CarbonImmutable $at): bool
    {
        $created = self::date($row->created_at);
        $ended = self::endedAt($row);

        return $created !== null && $created->lessThanOrEqualTo($at) && ($ended === null || $ended->greaterThan($at));
    }

    private static function inTrial(object $row, CarbonImmutable $at): bool
    {
        $trialEnds = self::date($row->trial_ends_at);

        return $trialEnds !== null && $trialEnds->greaterThan($at);
    }

    private static function endedAt(object $row): ?CarbonImmutable
    {
        $dates = array_filter([self::date($row->cancelled_at), self::date($row->expired_at)]);

        return $dates === [] ? null : min($dates);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
