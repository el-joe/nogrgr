<?php

use App\Modules\Reporting\DTOs\ReportFilterData;
use App\Modules\Reporting\Support\ReportFormat;

/*
| RQA-VEN-005: the period subtitle names the start year when the range spans two years
| (vendor dashboard "12 Months": 1 Dec 2025 – 27 Nov 2026), and only the end year otherwise.
*/

it('names both years when the period spans two years', function (): void {
    app()->setLocale('en');

    expect(ReportFormat::period(new ReportFilterData('2025-12-01', '2026-11-27')))->toBe('1 Dec 2025 – 27 Nov 2026');
});

it('names the year once when the period stays in one year', function (): void {
    app()->setLocale('en');

    expect(ReportFormat::period(new ReportFilterData('2026-10-29', '2026-11-27')))->toBe('29 Oct – 27 Nov 2026');
});
