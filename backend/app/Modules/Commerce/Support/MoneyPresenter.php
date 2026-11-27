<?php

namespace App\Modules\Commerce\Support;

use App\Modules\Localization\Models\Currency;
use App\Modules\Localization\Repositories\Contracts\CurrencyRepositoryContract;
use App\Shared\Money\Money;
use App\Shared\Support\Locale;
use NumberFormatter;

/**
 * Money in Store API responses: the stored USD minor units (the only amounts the server
 * computes with), formatted, and — when the client asked for an active display currency
 * (`X-Currency`) — a display-only conversion (Localization currency rate).
 */
final class MoneyPresenter
{
    private ?Currency $display = null;

    public function __construct(private readonly CurrencyRepositoryContract $currencies) {}

    public function useDisplayCurrency(?string $code): void
    {
        $currency = $code !== null && $code !== '' ? $this->currencies->findByCode(strtoupper($code)) : null;
        $this->display = $currency !== null && $currency->is_active && ! $currency->isBase() ? $currency : null;
    }

    /**
     * @return array{amount: int, currency: string, formatted: string, display: array{currency: string, amount: float, formatted: string}|null}
     */
    public function present(Money|int $amount): array
    {
        $money = $amount instanceof Money ? $amount : Money::ofMinor($amount);
        $display = null;

        if ($this->display !== null) {
            $converted = $this->display->convertFromBase($money);
            $formatter = new NumberFormatter(Locale::numberLocale(), NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($converted, $this->display->code);
            $display = [
                'currency' => $this->display->code,
                'amount' => $converted,
                'formatted' => $formatted === false ? $this->display->code.' '.$converted : $formatted,
            ];
        }

        return [
            'amount' => $money->minor,
            'currency' => $money->currency,
            'formatted' => $money->format(),
            'display' => $display,
        ];
    }

    /**
     * @return array{code: string, symbol: string, rate: string}|null
     */
    public function displayCurrency(): ?array
    {
        return $this->display !== null ? ['code' => $this->display->code, 'symbol' => $this->display->symbol, 'rate' => (string) $this->display->rate] : null;
    }
}
