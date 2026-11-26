<?php

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Enums\ReportBucket;
use App\Modules\Reporting\Support\ReportMath;
use App\Modules\Reporting\Support\SubscriptionMetrics;
use Carbon\CarbonImmutable;

/*
| Reporting aggregation math (ADM-070): ratios, deltas, bucketing, windows, MRR / churn.
*/

it('computes percentages and deltas safely', function (): void {
    expect(ReportMath::percent(1, 3))->toBe(33.3)
        ->and(ReportMath::percent(5, 0))->toBe(0.0)
        ->and(ReportMath::delta(110, 100))->toBe(10.0)
        ->and(ReportMath::delta(90, 100))->toBe(-10.0)
        ->and(ReportMath::delta(0, 0))->toBe(0.0)
        ->and(ReportMath::delta(5, 0))->toBeNull()
        ->and(ReportMath::average(1001, 2))->toBe(501)
        ->and(ReportMath::average(500, 0))->toBe(0);
});

it('buckets daily values into a zero-filled daily series', function (): void {
    $filters = new ReportFilterData('2026-11-01', '2026-11-05');

    expect($filters->days())->toBe(5)
        ->and($filters->bucket())->toBe(ReportBucket::Day)
        ->and(ReportMath::series($filters, ['2026-11-02' => 3, '2026-11-05' => 7, '2026-10-31' => 99]))->toBe([0, 3, 0, 0, 7]);
});

it('switches to monthly buckets beyond 62 days', function (): void {
    $filters = new ReportFilterData('2026-01-15', '2026-04-10');

    expect($filters->bucket())->toBe(ReportBucket::Month)
        ->and($filters->bucketKeys())->toBe(['2026-01', '2026-02', '2026-03', '2026-04'])
        ->and(ReportMath::series($filters, ['2026-01-20' => 2, '2026-01-31' => 3, '2026-03-01' => 4]))->toBe([5, 0, 4, 0]);
});

it('derives the previous window of the same length', function (): void {
    $previous = (new ReportFilterData('2026-11-11', '2026-11-20', 3, 7))->previous();

    expect($previous->start()->toDateString())->toBe('2026-11-01')
        ->and($previous->end()->toDateString())->toBe('2026-11-10')
        ->and($previous->tenantId)->toBe(3)
        ->and($previous->countryId)->toBe(7);
});

it('defaults to the last 30 days and clamps an inverted window', function (): void {
    CarbonImmutable::setTestNow('2026-11-26 10:00:00');

    expect((new ReportFilterData)->start()->toDateString())->toBe('2026-10-28')
        ->and((new ReportFilterData('2026-12-10', '2026-11-20'))->start()->toDateString())->toBe('2026-11-20');

    CarbonImmutable::setTestNow();
});

it('folds small donut slices into others', function (): void {
    expect(ReportMath::topSlices(['a' => 5, 'b' => 4, 'c' => 3, 'd' => 2, 'e' => 1], 3, 'Others'))->toBe(['a' => 5, 'b' => 4, 'Others' => 6]);
});

it('computes MRR, churn and trials from subscription lifecycles', function (): void {
    $row = static fn (array $attributes): object => (object) array_merge([
        'plan_id' => 1, 'plan_name' => 'Pro', 'term' => 'monthly', 'status' => 'active', 'amount_minor' => 5000,
        'trial_ends_at' => null, 'created_at' => '2026-01-01 00:00:00', 'cancelled_at' => null, 'expired_at' => null,
    ], $attributes);
    $rows = collect([
        $row([]),
        $row(['term' => 'yearly', 'amount_minor' => 60000, 'plan_name' => 'Business']),
        $row(['cancelled_at' => '2026-11-15 00:00:00']),
        $row(['trial_ends_at' => '2026-12-10 00:00:00', 'created_at' => '2026-11-20 00:00:00']),
        $row(['amount_minor' => 0]),
    ]);
    $start = CarbonImmutable::parse('2026-11-01');
    $end = CarbonImmutable::parse('2026-11-30 23:59:59');

    expect(SubscriptionMetrics::monthlyAmount('yearly', 60000))->toBe(5000)
        ->and(SubscriptionMetrics::mrr($rows, $start))->toBe(15000)
        ->and(SubscriptionMetrics::mrr($rows, $end))->toBe(10000)
        ->and(SubscriptionMetrics::churned($rows, $start, $end))->toBe(1)
        ->and(SubscriptionMetrics::churnRate($rows, $start, $end))->toBe(33.3)
        ->and(SubscriptionMetrics::trials($rows, $end))->toBe(1)
        ->and(SubscriptionMetrics::started($rows, $start, $end))->toBe(1);

    $plans = SubscriptionMetrics::byPlan($rows, $start, $end, 'No plan')->keyBy('plan');
    expect($plans['Pro']->active)->toBe(1)
        ->and($plans['Pro']->churned)->toBe(1)
        ->and($plans['Pro']->trials)->toBe(1)
        ->and($plans['Business']->mrr)->toBe(5000);
});
