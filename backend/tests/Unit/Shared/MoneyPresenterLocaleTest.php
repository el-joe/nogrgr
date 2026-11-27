<?php

use App\Modules\Commerce\Support\MoneyPresenter;
use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\Money\Money;

it('presents Arabic formatted and display amounts with Latin digits (RQA-SF-003)', function (): void {
    $currency = new Currency;
    $currency->forceFill(['code' => 'SAR', 'symbol' => 'SAR', 'rate' => '3.75', 'decimals' => 2, 'is_active' => true, 'is_base' => false]);

    $repository = Mockery::mock(CurrencyRepositoryContract::class);
    $repository->shouldReceive('findByCode')->with('SAR')->andReturn($currency);

    $presenter = new MoneyPresenter($repository);
    $presenter->useDisplayCurrency('sar');

    app()->setLocale('ar');
    $arabic = $presenter->present(Money::ofMinor(13708));

    expect($arabic['formatted'])->toContain('137')
        ->and(preg_match('/[\x{0660}-\x{0669}]/u', $arabic['formatted']))->toBe(0)
        ->and($arabic['display']['formatted'])->toContain('514')
        ->and(preg_match('/[\x{0660}-\x{0669}]/u', $arabic['display']['formatted']))->toBe(0);

    app()->setLocale('en');
    $english = $presenter->present(Money::ofMinor(13708));

    expect($english['formatted'])->toBe('$137.08')
        ->and($english['display']['formatted'])->toContain('514.05');
});
