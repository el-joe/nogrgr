<?php

use App\Modules\Shipping\Services\DeliveryDateEstimator;
use App\Modules\Shipping\Support\StopPeriodRule;
use Carbon\CarbonImmutable;

/*
| DeliveryDateEstimator: N delivery days after the order date, skipping weekly off days and
| stop periods (holidays), with the order cut-off.
*/

function estimator(): DeliveryDateEstimator
{
    return new DeliveryDateEstimator;
}

it('golden: reproduces the Figma estimator example (order Thursday, 1–3 days, Friday off, stop on Saturday)', function (): void {
    $result = estimator()->estimate(
        // Figma 71:46296 "Thu 25 Dec" → Sun 28 – Tue 30 (skips Fri + stop period Sat); 2026's Thursday is the 24th.
        CarbonImmutable::parse('2026-12-24 10:00'),
        1,
        3,
        [StopPeriodRule::range('2026-12-26', null, 'Hub maintenance')],
        [CarbonImmutable::FRIDAY],
    );

    expect($result->minDate->toDateString())->toBe('2026-12-27')
        ->and($result->minDate->isSunday())->toBeTrue()
        ->and($result->maxDate->toDateString())->toBe('2026-12-29')
        ->and($result->skipped)->toBe([['date' => '2026-12-25', 'reason' => null], ['date' => '2026-12-26', 'reason' => 'Hub maintenance']]);
});

it('counts calendar days when nothing is stopped', function (): void {
    $result = estimator()->estimate(CarbonImmutable::parse('2026-11-24 09:00'), 2, 5);

    expect($result->minDate->toDateString())->toBe('2026-11-26')
        ->and($result->maxDate->toDateString())->toBe('2026-11-29')
        ->and($result->skipped)->toBe([]);
});

it('pushes the whole window past a multi-day holiday range', function (): void {
    $result = estimator()->estimate(CarbonImmutable::parse('2026-11-30'), 1, 3, [StopPeriodRule::range('2026-12-01', '2026-12-02', 'National day')]);

    expect($result->minDate->toDateString())->toBe('2026-12-03')
        ->and($result->maxDate->toDateString())->toBe('2026-12-05');
});

it('applies weekly stop periods only inside their date range', function (): void {
    $fridays = StopPeriodRule::weekly(CarbonImmutable::FRIDAY, '2026-12-01', '2026-12-31', 'Every Friday');

    expect(estimator()->estimate(CarbonImmutable::parse('2026-12-03'), 1, 1, [$fridays])->minDate->toDateString())->toBe('2026-12-05')
        ->and(estimator()->estimate(CarbonImmutable::parse('2027-01-07'), 1, 1, [$fridays])->minDate->toDateString())->toBe('2027-01-08')
        ->and(estimator()->estimate(CarbonImmutable::parse('2026-11-26'), 1, 1, [$fridays])->minDate->toDateString())->toBe('2026-11-27');
});

it('starts the next day for orders at or after the cut-off', function (): void {
    $before = estimator()->estimate(CarbonImmutable::parse('2026-11-24 13:59'), 1, 2, [], [], '14:00');
    $after = estimator()->estimate(CarbonImmutable::parse('2026-11-24 14:00'), 1, 2, [], [], '14:00');

    expect($before->minDate->toDateString())->toBe('2026-11-25')
        ->and($after->startDate->toDateString())->toBe('2026-11-25')
        ->and($after->minDate->toDateString())->toBe('2026-11-26')
        ->and($after->maxDate->toDateString())->toBe('2026-11-27');
});

it('returns the start day for a zero-day minimum and is deterministic', function (): void {
    $first = estimator()->estimate(CarbonImmutable::parse('2026-11-24'), 0, 1);
    $second = estimator()->estimate(CarbonImmutable::parse('2026-11-24'), 0, 1);

    expect($first->minDate->toDateString())->toBe('2026-11-24')->and($first->toArray())->toBe($second->toArray());
});

it('lists the dates of delivery days 1..N for the popup', function (): void {
    $days = estimator()->deliveryDays(CarbonImmutable::parse('2026-11-24'), 4, [], [CarbonImmutable::FRIDAY]);

    expect(array_map(fn ($date) => $date->toDateString(), $days))->toBe([1 => '2026-11-25', 2 => '2026-11-26', 3 => '2026-11-28', 4 => '2026-11-29']);
});

it('rejects an invalid window and a calendar that never delivers', function (): void {
    expect(fn () => estimator()->estimate(CarbonImmutable::parse('2026-11-24'), 3, 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => estimator()->estimate(CarbonImmutable::parse('2026-11-24'), 1, 1, [], [0, 1, 2, 3, 4, 5, 6]))->toThrow(InvalidArgumentException::class);
});
