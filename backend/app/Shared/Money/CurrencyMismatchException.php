<?php

namespace App\Shared\Money;

use InvalidArgumentException;

class CurrencyMismatchException extends InvalidArgumentException
{
    public function __construct(string $expected, string $given)
    {
        parent::__construct("Currency mismatch: expected [{$expected}], got [{$given}].");
    }
}
