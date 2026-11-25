<?php

use App\Shared\Money\CurrencyMismatchException;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;

function moneyCastModel(): Model
{
    return new class extends Model
    {
        protected $guarded = [];

        protected function casts(): array
        {
            return [
                'total' => MoneyCast::class,
                'fee' => MoneyCast::class.':USD',
            ];
        }
    };
}

it('reads minor units with the currency column', function (): void {
    $model = moneyCastModel()->setRawAttributes(['total' => 1999, 'currency' => 'SAR']);

    expect($model->total)->toBeInstanceOf(Money::class)
        ->and($model->total->minor)->toBe(1999)
        ->and($model->total->currency)->toBe('SAR');
});

it('writes minor units and the currency column', function (): void {
    $model = moneyCastModel();
    $model->total = Money::of('10.50', 'SAR');

    expect($model->getAttributes())->toMatchArray(['total' => 1050, 'currency' => 'SAR']);
});

it('supports a fixed currency', function (): void {
    $model = moneyCastModel()->setRawAttributes(['fee' => 250]);

    expect($model->fee->currency)->toBe('USD');
});

it('rejects a currency different from the fixed one', function (): void {
    $model = moneyCastModel();
    $model->fee = Money::ofMinor(1, 'SAR');
})->throws(CurrencyMismatchException::class);

it('keeps nulls', function (): void {
    $model = moneyCastModel()->setRawAttributes(['total' => null, 'currency' => 'SAR']);

    expect($model->total)->toBeNull();
});
